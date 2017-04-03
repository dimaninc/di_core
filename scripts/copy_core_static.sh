#!/usr/bin/env bash
parent_path=$( cd "$(dirname "${BASH_SOURCE[0]}")" ; pwd -P )
cd "$parent_path"

if [ -d "../../../../vendor" ]
then
	src_path="../../../../vendor/dimaninc/di_core"
	dst_path="../../../../htdocs/assets"
	css_dst_path="$dst_path"
else
	src_path="../vendor/dimaninc/di_core"
	dst_path="../htdocs/assets"
	css_dst_path="../../../$dst_path"
fi

echo "Copying CSS"
mkdir -p "$dst_path/styles/_core"
#yes | cp -rf "$src_path/css/." "$dst_path/styles/_core"
cd "$src_path/css"
find . -name \*.css -exec cp --parents {} "$css_dst_path/styles/_core" \;
cd -
#cd "$parent_path"

echo "Copying Fonts"
yes | cp -rf "$src_path/fonts" "$dst_path"

echo "Copying Images"
mkdir -p "$dst_path/images/_core"
yes | cp -rf "$src_path/i/." "$dst_path/images/_core"

echo "Copying JS"
mkdir -p "$dst_path/js/_core"
#yes | cp -rf "$src_path/js/." "$dst_path/js/_core"
cd "$src_path/js"
find . -name \*.js -exec cp --parents {} "$css_dst_path/js/_core" \;
cd -

echo "Copying Vendor libs"
yes | cp -rf "$src_path/vendor" "$dst_path"

echo -e "\e[0;32mAll static stuff has been copied"
