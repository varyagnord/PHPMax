set shell := ["bash", "-uc"]

default:
    @just --list

list:
    @just --list

status:
    @git status --short --branch

agents-check:
    @cmp AGENTS.md GEMINI.md
    @printf 'AGENTS.md and GEMINI.md are identical.\n'

docs-list:
    @find docs/phpmax -maxdepth 1 -type f | sort

contract-check:
    @php tools/contract-manifest.php check

release-zip:
    @php tools/build-release.php

release-check:
    @php tools/build-release.php --check

docs-guard:
    @changed="$(git status --porcelain | sed 's/^...//')"; \
    source_changed="$(printf '%s\n' "$changed" | grep -E '^(src/PHPMax/|tests-php/|composer\.json|composer\.lock|phpunit\.xml|phpstan\.neon|Justfile|\.github/workflows/|\.github/pull_request_template\.md|src/pymax/|tests/)' || true)"; \
    docs_changed="$(printf '%s\n' "$changed" | grep -E '^(docs/phpmax/|AGENTS\.md|GEMINI\.md|README\.md)' || true)"; \
    if [ -n "$source_changed" ] && [ -z "$docs_changed" ] && [ "${DOCS_REVIEWED:-0}" != "1" ]; then \
        printf 'Source files changed, but docs did not change.\n'; \
        printf 'Review whether documentation must be updated.\n'; \
        printf 'If docs are truly unnecessary, rerun with DOCS_REVIEWED=1.\n'; \
        printf '%s\n' "$source_changed"; \
        exit 1; \
    fi; \
    if [ -n "$source_changed" ] && [ -z "$docs_changed" ]; then \
        printf 'Source files changed; docs were reviewed and intentionally left unchanged.\n'; \
    elif [ -n "$source_changed" ]; then \
        printf 'Source and docs changes are both present.\n'; \
    else \
        printf 'No source changes requiring documentation review.\n'; \
    fi

upstream-reminder:
    @printf 'Before a milestone or protocol/auth/session/security work, run the upstream PyMax audit from docs/phpmax/upstream-sync.md.\n'

tool-versions:
    @printf 'PHP: '; php -r 'echo PHP_VERSION, PHP_EOL;' || true
    @printf 'just: '; just --version || true
    @printf 'Composer: '; if command -v composer >/dev/null 2>&1; then composer --version; else printf 'not installed\n'; fi
    @printf 'uv: '; if command -v uv >/dev/null 2>&1; then uv --version; else printf 'not installed\n'; fi

php-check:
    @find src/PHPMax tests-php tools -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
    @php tools/php74-compat-check.php
    @php tools/contract-manifest.php check
    @if [ -f composer.json ] && command -v composer >/dev/null 2>&1; then \
        composer validate --strict; \
    else \
        printf 'Composer is not installed; skipping composer validate.\n'; \
    fi
    @php tools/run-php-tests.php

integration-check:
    @php tools/integration-check.php

integration-plan:
    @php tools/integration-check.php --plan

python-baseline:
    @if command -v uv >/dev/null 2>&1; then \
        uv run pytest; \
    else \
        printf 'uv is not installed; skipping Python reference baseline tests.\n'; \
    fi

doctor: agents-check docs-guard contract-check tool-versions upstream-reminder

pre-publish-check: agents-check docs-guard php-check release-check status
