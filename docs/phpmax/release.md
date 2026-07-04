# PHPMax Release ZIP

PHPMax должен уметь поставляться на shared hosting, где нет shell-доступа и
нельзя выполнить `composer install`.

## Команды

```bash
just release-check
just release-zip
php tools/build-release.php --check
php tools/build-release.php --output=dist/phpmax-dev.zip
php tools/build-release.php --dry-run
```

`just pre-publish-check` не создает архив автоматически, но запускает
`just release-check` и проверяет builder через tests. Перед публикацией release
archive нужно собрать отдельной командой `just release-zip`.

## GitHub Actions

`.github/workflows/publish.yml` собирает PHPMax release ZIP на PHP 7.4:

- запускает `just pre-publish-check`;
- выполняет `just release-zip`;
- сохраняет ZIP как workflow artifact;
- при событии `release: published` прикладывает ZIP к GitHub Release.

Старая PyPI-публикация отключена: PHPMax публикуется как PHP runtime ZIP и
Composer package metadata, а Python package остается только reference-кодом в
этом репозитории.

## Состав архива

Release ZIP содержит runtime-часть PHPMax:

- `autoload.php` - fallback PSR-4 autoloader для окружений без Composer;
- `composer.json`;
- `LICENSE`, `README.md`, если они есть; корневой `README.md` должен оставаться
  PHPMax quick start, потому что он является публичным входом в release ZIP;
- `src/PHPMax`;
- `docs/phpmax`;
- `vendor`, если директория существует.

Python reference `src/pymax`, tests, tooling и dev-only files в архив не
попадают.

## Vendor policy

Сейчас PHPMax не имеет runtime Composer packages: в `require` только `php` и
PHP extensions. Поэтому архив может работать без `vendor`.

Если в будущем появятся runtime packages, `tools/build-release.php` будет
требовать существующий `vendor/` и завершится ошибкой, пока разработчик не
соберет dependencies через:

```bash
composer install --no-dev --classmap-authoritative
```

После этого `just release-zip` включит `vendor/` в архив.

## Проверка

Release builder покрыт lightweight тестом:

- dry-run должен вернуть JSON manifest состава архива;
- `--check` должен проверить manifest/vendor policy без создания ZIP;
- реальный ZIP собирается через `ext-zip` или системный `zip`;
- archive listing проверяется через `unzip`, если команда доступна;
- архив должен содержать `autoload.php`, `composer.json`, `src/PHPMax` и
  `docs/phpmax`;
- архив не должен содержать `src/pymax` и `tests-php`;
- распакованный архив должен запускать fallback `autoload.php` и загружать
  runtime classes без Composer: public `Client`/`WebClient`, transport
  classes, JSON session store и file/photo helpers.
