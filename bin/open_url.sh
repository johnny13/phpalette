#!/usr/bin/env bash

if [ -z "$1" ]; then
    echo
    echo "[FAIL]  No URL supplied!"
    echo
    exit 1
fi

URL=$1
[[ -x $BROWSER ]] && exec "$BROWSER" "$URL"
path=$(which xdg-open || which gnome-open) && exec "$path" "$URL"
echo "Can't find browser"
