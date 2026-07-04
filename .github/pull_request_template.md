## Описание

Кратко, что делает этот PR для PHPMax: переносит PyMax parity, исправляет
payload/runtime behavior, обновляет документацию или tooling.

## Тип изменений

- [ ] Исправление бага
- [ ] Новая функциональность
- [ ] Улучшение документации
- [ ] Рефакторинг
- [ ] Parity-перенос из PyMax

## Связанные задачи / Issue

Ссылка на issue, если есть: #

## Тестирование

- [ ] `just pre-publish-check`
- [ ] Если менялись protocol/model/payload слои, обновлены parity fixtures.
- [ ] Если менялись исходники, документация обновлена или явно проверена.
- [ ] Если менялся release/runtime surface, проверен `just release-zip`.

Пример PHP-кода для проверки behavior, если нужен:

```php
<?php

require __DIR__ . '/autoload.php';
```
