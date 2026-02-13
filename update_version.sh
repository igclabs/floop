#!/bin/bash

# Navigate to the project root directory if the script is not run from there
cd "$(dirname "$0")"

# Read the current version
VERSION=$(cat version.txt)

# Increment the version (assumes semantic versioning)
IFS='.' read -ra VER <<< "$VERSION"
VER[2]=$((VER[2]+1))
NEW_VERSION="${VER[0]}.${VER[1]}.${VER[2]}"

# Update the version file
echo $NEW_VERSION > version.txt

# Check if a commit message was passed as an argument
COMMIT_MESSAGE="$1"
if [ -z "$COMMIT_MESSAGE" ]; then
  COMMIT_MESSAGE="Increase Version Number to $NEW_VERSION"
fi

# Add all changes to git
git add .

# Commit changes with the passed or default message
git commit -m "$COMMIT_MESSAGE"

# Create a new tag for the new version
git tag "v$NEW_VERSION"

# Push the changes and tags
git push gitlab --tags
git push origin --tags

# Confirmation message
echo "Release $NEW_VERSION pushed successfully."