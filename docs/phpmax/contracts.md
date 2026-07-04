# PHPMax Contract Manifest

`docs/phpmax/contracts.json` - машинно проверяемый manifest контрактов,
снятый с локального Python reference `src/pymax`.

## Что покрывает manifest

- `commands` - значения `Command` из `src/pymax/protocol/enums.py`.
- `opcodes` - значения `Opcode` из `src/pymax/protocol/enums.py`.
- `event_types` - значения `EventType` из `src/pymax/dispatch/enums.py`.
- `event_map` - opcodes, которые PyMax route-ит через dispatch resolver.
- `domain_enums` - значения PyMax domain enum-like classes:
  `ChatType`, `AccessType`, `MessageStatus`, `AttachmentType`,
  `TranscriptionStatus`.
- `api_enums` - значения PyMax service/API enums из `src/pymax/api/*/enums.py`,
  включая auth/chat/message/session/user constants и payload key anchors.
- `payload_models` - поля payload classes из `src/pymax/api/*/payloads.py`
  и карта Python field name -> serialized payload key после PyMax
  `to_camel`/`Field(alias=...)`/`Field(serialization_alias=...)`.
- `domain_models` - domain/response/attachment model fields/serialized keys
  для перенесенных моделей из `src/pymax/types/domain`, включая auth
  responses, sync/session/folder state, text elements, attachments и download
  response objects.
- `event_models` - typed event model fields/serialized keys для dispatcher
  событий и upload processing signals.
- `service_methods` - публичные methods из `src/pymax/api/*/service.py` и
  ожидаемые PHP method names для service surface parity.
- `client_methods` - публичные methods из PyMax `src/pymax/infra/*Mixin` и
  ожидаемые PHP `Client` shortcut names.
- `service_method_params` - параметры публичных PyMax service methods и
  ожидаемые PHP parameter names/order после snake_case -> camelCase.
- `client_method_params` - параметры публичных PyMax client mixin methods и
  ожидаемые PHP `Client` parameter names/order.

На текущей reference-версии `maxapi-python 2.3.1` manifest фиксирует:

- 4 command values;
- 164 opcodes;
- 13 event types;
- 9 dispatch event map entries;
- 5 domain enum groups, 25 enum values;
- 16 API enum groups, 47 enum values;
- 71 payload model anchors with field and serialized payload key metadata.
- 46 domain/response/attachment model anchors with field and serialized
  payload key metadata.
- 7 typed event model anchors with field and serialized payload key metadata.
- 8 API service domains, 77 public service method/name mappings and 77 public
  service parameter mappings.
- 59 public client mixin method/name mappings and 59 public client parameter
  mappings.

## Как обновлять

После обновления `src/pymax` или изменения PHP constants/event resolver/domain,
API constant classes, payload/domain schemas, service/client method names,
service/client method parameters или public client shortcuts:

```bash
php tools/contract-manifest.php write
just contract-check
```

`just php-check` и `just pre-publish-check` запускают `contract-check`
автоматически. Если manifest устарел, gate должен падать до публикации.

## Проверка contract parity

`just contract-check` теперь не только сверяет JSON с Python reference, но и:

- через reflection проверяет, что перенесённые PHP payload classes сериализуют
  тот же набор top-level ключей, что PyMax;
- проверяет, что покрытые PHP domain/response/attachment models сериализуют
  тот же набор top-level ключей, что PyMax;
- проверяет, что typed PHP event models сериализуют тот же набор top-level
  ключей, что PyMax;
- проверяет, что публичные PyMax API service methods имеют PHP equivalents.
- проверяет, что публичные PyMax client mixin methods доступны как PHP
  `Client` shortcuts.
- проверяет, что параметры public service methods совпадают с PyMax по
  имени и порядку после PHP-идиоматичного camelCase.
- проверяет, что параметры public `Client` shortcuts совпадают с PyMax mixins
  по имени и порядку после PHP-идиоматичного camelCase.

Это ловит drift в aliases вроде `_type`, `mt_instanceid`, `from`, `LOGIN`,
chat settings option keys, domain keys вроде `firstName`/`lastName`,
storage-state keys вроде `chats_sync`/`config_hash` и появление новых upstream
event/service/client methods. Теперь gate также ловит signature drift вроде
`password_old` -> `passwordOld` и client wrapper names вроде
`from_time` -> `fromTime`.

## Границы проверки

Manifest не заменяет focused parity-тесты для вложенных payload значений,
defaults, type casting, signatures и service/client behavior. Он фиксирует
reference anchors и ловит drift в низкоуровневых enum/event/payload-key/service/
client surface контрактах. Для protocol/model/payload/service/client изменений
нужны focused tests и fixtures в дополнение к `contract-check`.
