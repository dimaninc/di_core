#!/usr/bin/env bash
# Releases a new di_core version: changelog, version in composer.json, commit, tag, push.
# Writes nothing before the final confirmation; --dry-run shows everything and exits.
#
#   bash scripts/release.sh [--dry-run]

set -euo pipefail

REMOTE=origin
BRANCH=master
# Service commits of previous releases stay out of the changelog
SKIP_SUBJECTS='^(Changelog for|Release) '

DRY_RUN=0
[[ "${1:-}" == "--dry-run" ]] && DRY_RUN=1

cd "$(dirname "$0")/.."

die() { echo "Error: $*" >&2; exit 1; }

# Some consoles (e.g. an IDE run window) send "y\r" instead of "y": trim and lowercase.
# The prompt goes to stderr, so the answer can be captured with $(...)
ask() {
    local reply=''
    read -r -p "$1" reply || true
    printf '%s' "$reply" | tr -d '[:space:]' | tr '[:upper:]' '[:lower:]'
}

# --- Checks: release only from a clean master that is not behind the remote ---

[[ "$(git rev-parse --abbrev-ref HEAD)" == "$BRANCH" ]] ||
    die "must be on branch $BRANCH"
[[ -z "$(git status --porcelain)" ]] ||
    die "working tree has uncommitted changes"

git fetch --quiet --tags "$REMOTE" "$BRANCH"
# Being ahead is fine (unpushed commits go out with the release); behind or diverged is not
git merge-base --is-ancestor "$REMOTE/$BRANCH" HEAD ||
    die "$BRANCH is behind or diverged from $REMOTE/$BRANCH, sync it first"

LAST_TAG="$(git describe --tags --abbrev=0 --match '[0-9]*.[0-9]*.[0-9]*')"
[[ "$LAST_TAG" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]] ||
    die "last tag '$LAST_TAG' is not in X.Y.Z format"
MAJOR="${BASH_REMATCH[1]}"
MINOR="${BASH_REMATCH[2]}"
PATCH="${BASH_REMATCH[3]}"

COMPOSER_VERSION="$(sed -n 's/^ *"version": *"\([^"]*\)".*/\1/p' composer.json)"
[[ -n "$COMPOSER_VERSION" ]] || die "composer.json has no version field"
if [[ "$COMPOSER_VERSION" != "$LAST_TAG" ]]; then
    echo "Warning: composer.json = $COMPOSER_VERSION, last tag = $LAST_TAG"
fi

COMMITS="$(git log --no-merges --reverse --format='%s' "$LAST_TAG"..HEAD)"
[[ -n "$COMMITS" ]] || die "no commits since $LAST_TAG"

# --- Release kind ---

echo "Last release: $LAST_TAG"
KIND="$(ask "Release kind: major / minor / [patch]: ")"
case "${KIND:-patch}" in
    major) NEW="$((MAJOR + 1)).0.0" ;;
    minor) NEW="$MAJOR.$((MINOR + 1)).0" ;;
    patch) NEW="$MAJOR.$MINOR.$((PATCH + 1))" ;;
    *) die "unknown kind '$KIND'" ;;
esac
git rev-parse -q --verify "refs/tags/$NEW" >/dev/null && die "tag $NEW already exists"

# --- Changelog: list of commit subjects ---

# grep with no matches exits 1, which would kill the script under set -e
CHANGES="$({ grep -vE "$SKIP_SUBJECTS" <<<"$COMMITS" || true; } | sed 's/^/- /')"
[[ -n "$CHANGES" ]] || die "only service commits since $LAST_TAG, nothing to release"

# --- Summary and the single point of no return ---

print_summary() {
    echo
    echo "===== Changelog $NEW ====="
    echo "$CHANGES"
    echo "=========================="
    echo
    echo "Version: $LAST_TAG -> $NEW"
    echo "Commit:  Release $NEW (CHANGELOG.md, composer.json)"
    echo "Tag:     $NEW"
    echo "Push:    $REMOTE $BRANCH + tag $NEW"
}

print_summary

if [[ "$DRY_RUN" == 1 ]]; then
    echo "--dry-run: nothing written."
    exit 0
fi

TMP="$(mktemp -t di_core_release.XXXXXX)"
trap 'rm -f "$TMP"' EXIT

while true; do
    ANSWER="$(ask "Release? [y] yes, [e] edit changelog, [N] cancel: ")"
    case "$ANSWER" in
        y) break ;;
        e)
            echo "$CHANGES" >"$TMP"
            "${EDITOR:-vi}" "$TMP"
            # $(...) strips trailing blank lines left by the editor
            EDITED="$(cat "$TMP")"
            if [[ -z "${EDITED//[[:space:]]/}" ]]; then
                echo "Changelog is empty, keeping the previous one."
            else
                CHANGES="$EDITED"
            fi
            print_summary
            ;;
        '' | n) echo "Cancelled, nothing changed."; exit 1 ;;
        # Anything unrecognised is shown escaped, so an invisible character is visible
        *) echo "Cancelled, nothing changed (got $(printf '%q' "$ANSWER"))."; exit 1 ;;
    esac
done

# The new section goes right under the "# Changelog" heading
[[ "$(head -n 1 CHANGELOG.md)" == "# Changelog" ]] ||
    die "CHANGELOG.md must start with '# Changelog'"
{
    echo "# Changelog"
    echo
    echo "## $NEW"
    echo
    echo "$CHANGES"
    # Line 2 of the file provides the blank separator before the previous section
    tail -n +2 CHANGELOG.md
} >"$TMP"
mv "$TMP" CHANGELOG.md

# Composer skips a tag that doesn't match version, so bump strictly before tagging
perl -0pi -e 's/("version":\s*")[^"]*(")/${1}'"$NEW"'${2}/' composer.json
grep -q "\"version\": \"$NEW\"" composer.json || die "failed to update composer.json"

git add CHANGELOG.md composer.json
git commit --quiet -m "Release $NEW"
git tag -a "$NEW" -m "$NEW"

# --atomic: the branch and the tag go out together or not at all
if ! git push --atomic "$REMOTE" "$BRANCH" "refs/tags/$NEW"; then
    echo
    echo "Push failed. The commit and the tag stay local."
    echo "Retry:     git push --atomic $REMOTE $BRANCH refs/tags/$NEW"
    echo "Roll back: git tag -d $NEW && git reset --hard HEAD~1"
    exit 1
fi

echo
echo "Done: $NEW released."
