#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 /path/to/laravel-project"
  exit 1
fi

TARGET="$1"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMPLATE_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

if [ ! -d "$TARGET" ]; then
  echo "Target directory does not exist: $TARGET"
  exit 1
fi

mkdir -p "$TARGET/docs" "$TARGET/.github" "$TARGET/app/Services" "$TARGET/app/Actions" "$TARGET/app/Repositories"

cp "$TEMPLATE_ROOT/README.md" "$TARGET/README.md"
cp "$TEMPLATE_ROOT/AGENTS.md" "$TARGET/AGENTS.md"
cp "$TEMPLATE_ROOT/CLAUDE.md" "$TARGET/CLAUDE.md"
cp "$TEMPLATE_ROOT/docs/"*.md "$TARGET/docs/"
cp "$TEMPLATE_ROOT/.github/copilot-instructions.md" "$TARGET/.github/copilot-instructions.md"
cp "$TEMPLATE_ROOT/.github/pull_request_template.md" "$TARGET/.github/pull_request_template.md"

touch "$TARGET/app/Services/.gitkeep" "$TARGET/app/Actions/.gitkeep" "$TARGET/app/Repositories/.gitkeep"

echo "Template copied to: $TARGET"
echo "Next: replace placeholders in docs/*.md and review AGENTS.md / CLAUDE.md / Copilot instructions."
