#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

echo "==> PHPUnit"
make phpunit

echo "==> PHPStan"
make phpstan

echo "==> PHP-CS-Fixer (dry run)"
make php-cs-fixer-check

echo "==> Git diff check"
git diff --check
