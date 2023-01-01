#!/bin/bash

. "$(dirname -- "${BASH_SOURCE[0]}")/../script/env"

default_request=(
  curl --silent -o -
    -X POST '{}/foo?param=value&pet=dog'
    -H 'Host: example.com'
    -H 'Date: Sun, 05 Jan 2014 21:31:40 GMT'
    -H 'Content-Type: application/json'
    -H 'Digest: SHA-256=X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE='
    -d '{"hello": "world"}'
)

set -x
[ VALID_BUT_INSECURE = "$(phps util/check_signature.php "${default_request[@]}" -H 'Signature: keyId="Test",algorithm="rsa-sha256",signature="SjWJWbWN7i0wzBvtPl8rbASWz5xQW6mcJmn+ibttBqtifLN7Sazz6m79cNfwwb8DMJ5cou1s7uEGKKCs+FLEEaDV5lp7q25WqS+lavg7T8hc0GppauB6hbgEKTwblDHYGEtbGmtdHgVCk9SuS13F0hZ8FD0k/5OxEPXe5WozsbM="')" ]
[ VALID_BUT_INSECURE = "$(phps util/check_signature.php "${default_request[@]}" -H 'Signature: keyId="Test",algorithm="rsa-sha256",headers="(request-target) host date",signature="qdx+H7PHHDZgy4y/Ahn9Tny9V3GP6YgBPyUXMmoxWtLbHpUnXS2mg2+SbrQDMCJypxBLSPQR2aAjn7ndmw2iicw3HMbe8VfEdKFYRqzic+efkb3nndiv/x1xSHDJWeSWkx3ButlYSuBskLu6kd9Fswtemr3lgdDEmn04swr2Os0="')" ]
[ VALID_BUT_INSECURE = "$(phps util/check_signature.php "${default_request[@]}" -H 'Signature: keyId="Test",algorithm="rsa-sha256",headers="(request-target) host date content-type digest content-length",signature="vSdrb+dS3EceC9bcwHSo4MlyKS59iFIrhgYkz8+oVLEEzmYZZvRs8rgOp+63LEM3v+MFHB32NfpB2bEKBIvB1q52LaEUHFv120V01IL+TAD48XaERZFukWgHoBTLMhYS2Gb51gWxpeIq8knRmPnYePbF5MOkR0Zkly4zKH7s1dE="')" ]
