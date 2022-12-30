#!/bin/bash

. "$(dirname -- "${BASH_SOURCE[0]}")/../script/env"

args=(
  phps activitypub.php
    curl -o -
      -H 'Date: Sun, 05 Jan 2014 21:31:40 GMT'
      -H 'Signature: keyId="Test",algorithm="rsa-sha256",signature="SjWJWbWN7i0wzBvtPl8rbASWz5xQW6mcJmn+ibttBqtifLN7Sazz6m79cNfwwb8DMJ5cou1s7uEGKKCs+FLEEaDV5lp7q25WqS+lavg7T8hc0GppauB6hbgEKTwblDHYGEtbGmtdHgVCk9SuS13F0hZ8FD0k/5OxEPXe5WozsbM="'
      -d '{"hello": "world"}' {}
)
"${args[@]}"
