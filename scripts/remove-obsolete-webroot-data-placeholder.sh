#!/bin/sh
set -eu

# Run from the BASE3 application root after installing the fixed data path.
placeholder='data/.gitignore'
if [ ! -f "$placeholder" ]; then
    exit 0
fi

if [ "$(cat "$placeholder")" = "$(printf '*\n!.gitignore')" ]; then
    rm "$placeholder"
    rmdir data 2>/dev/null || :
fi
