#!/bin/bash

PLUGIN_FILE="migrate-blog-posts.php"
ZIP_NAME="migrate-blog-posts.zip"

VERSION_LINE=$(grep -m1 "^Version:" "$PLUGIN_FILE")
CURRENT_VERSION=$(echo "$VERSION_LINE" | awk '{print $2}')

IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"
PATCH=$((PATCH + 1))
NEW_VERSION="$MAJOR.$MINOR.$PATCH"

sed -i '' "s/^Version: $CURRENT_VERSION/Version: $NEW_VERSION/" "$PLUGIN_FILE"

CURRENT_DIR="$(basename "$PWD")"
zip -r ../$ZIP_NAME . -x "*.git*" -x "*.zip"

mv ../$ZIP_NAME ./

echo "Version updated to $NEW_VERSION and $ZIP_NAME created."