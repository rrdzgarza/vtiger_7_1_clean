#!/bin/bash
# 99-final-checks.sh
# Final safety checks before Apache starts.

echo "---------------------------------------------------"
echo " [HOOK] 99-final-checks.sh: Safety Net"
echo "---------------------------------------------------"

# FINAL PERMISSIONS CHECK
# Just to ensure the root folder is owned by www-data if hooks missed it
# or if previous scripts created files as root.
if [ "$SKIP_PERMISSIONS" = "true" ]; then
    echo "⚠️  SKIP_PERMISSIONS=true. Skipping final chown."
else
    if [ -d "/var/www/html" ]; then
        echo " -> Ensuring /var/www/html ownership..."
        chown www-data:www-data /var/www/html
    fi
fi

echo "✅ [HOOK] Ready for startup."
