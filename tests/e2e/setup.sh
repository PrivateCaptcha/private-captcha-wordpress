#!/bin/sh
set -eu

: "${PC_API_KEY:?Set PC_API_KEY in docker/.env.e2e}"
: "${PC_SITEKEY:?Set PC_SITEKEY in docker/.env.e2e}"

if [ "$PC_SITEKEY" = "aaaaaaaabbbbccccddddeeeeeeeeeeee" ]; then
    printf '%s\n' 'Use a real sitekey: the current PHP SDK rejects the dummy sitekey verification code.' >&2
    exit 1
fi

if ! wp core is-installed; then
    wp core install --url=http://localhost:18081 --title="Private Captcha e2e" \
        --admin_user=admin --admin_password=e2e-password \
        --admin_email=admin@example.org --skip-email
fi

installed_wpforms_version=''
if wp plugin is-installed wpforms-lite; then
    installed_wpforms_version="$(wp plugin get wpforms-lite --field=version)"
fi
if [ "$installed_wpforms_version" != '2.0.2.1' ]; then
    wp plugin install wpforms-lite --version=2.0.2.1 --force
fi

if ! wp plugin is-installed contact-form-7; then
    wp plugin install contact-form-7 --version=6.1.7
fi

if ! wp plugin is-installed woocommerce; then
    wp plugin install woocommerce --version=10.9.4
fi

installed_forminator_version=''
if wp plugin is-installed forminator; then
    installed_forminator_version="$(wp plugin get forminator --field=version)"
fi
if [ "$installed_forminator_version" != '1.57.3' ]; then
    wp plugin install forminator --version=1.57.3 --force
fi

installed_formidable_version=''
if wp plugin is-installed formidable; then
    installed_formidable_version="$(wp plugin get formidable --field=version)"
fi
if [ "$installed_formidable_version" != '7.0' ]; then
    wp plugin install formidable --version=7.0 --force
fi

wp plugin activate wpforms-lite contact-form-7 woocommerce forminator formidable private-captcha
wp theme activate twentytwentyfive
wp --user=admin eval-file /e2e/seed.php
wp --user=admin eval-file /e2e/seed-contactform7.php
wp --user=admin eval-file /e2e/seed-woocommerce.php
wp --user=admin eval-file /e2e/seed-forminator.php
wp --user=admin eval-file /e2e/seed-formidable.php
