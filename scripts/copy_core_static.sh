#!/usr/bin/env bash
lib_path="vendor/dimaninc/di_core"
root_path=$(pwd)
COLOR_GREEN='\033[0;32m'
COLOR_NO='\033[0m'
src_path=$lib_path
dst_path="htdocs/assets"
rsync=false

if command -v rsync >/dev/null 2>&1; then
	rsync=true
fi
if type rsync >/dev/null 2>&1; then
	rsync=true
fi

if [ ! -d $lib_path ]; then
	echo "diCore not found. Is script being executed from project root?"
	exit
fi

echo "Copying CSS"
mkdir -p "$dst_path/styles/_core"
if [ "$rsync" = true ]; then
	rsync -a --include '*/' --include '*.css' --exclude '*' "$src_path/css/" "$dst_path/styles/_core/"
else
	cd "$src_path/css"
	find . -name \*.css -exec cp --parents \{\} "${root_path}/${dst_path}/styles/_core" \;
	cd - >/dev/null 2>&1
fi

echo "Copying Fonts"
yes | cp -rf "$src_path/fonts" "$dst_path"

echo "Copying Images"
mkdir -p "$dst_path/images/_core"
yes | cp -rf "$src_path/i/." "$dst_path/images/_core"

echo "Copying JS"
mkdir -p "$dst_path/js/_core"
if [ "$rsync" = true ]; then
	rsync -a --include '*/' --include '*.js' --exclude '*' "$src_path/js/" "$dst_path/js/_core/"
else
	cd "$src_path/js"
	find . -name \*.js -exec cp --parents \{\} "${root_path}/${dst_path}/js/_core" \;
	cd - >/dev/null 2>&1
fi

echo "Copying Vendor libs"
yes | cp -rf "$src_path/vendor" "$dst_path"

printf "${COLOR_GREEN}All core assets have been copied${COLOR_NO}\n"
