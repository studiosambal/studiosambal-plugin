#!/bin/sh
# Draait alle testmodi. WP_CORE_DIR wijst naar een lokale WordPress-installatie.
set -e
cd "$(dirname "$0")/.."
for mode in valid unset invalid credentials query fragment whitespace; do
   php tests/omgeving-test.php "$mode"
done
