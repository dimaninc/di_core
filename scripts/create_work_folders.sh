#!/usr/bin/env bash
mkdir -p _admin/_inc/cache
chmod -R 777 _admin/_inc/cache

mkdir -p _cfg/cache
chmod -R 777 _cfg/cache

mkdir -p db/dump
chmod -R 777 db/dump

mkdir -p htdocs/uploads
chmod -R 777 htdocs/uploads

mkdir -p log/db
chmod -R 777 log/db

mkdir -p log/debug
chmod -R 777 log/debug
