#!/usr/bin/env sh

createAppSettings() {
    cp -n $PROJECT_DIR/config-sample.php $PROJECT_DIR/config.php
    sed -i "s/DB_HOST       = '.*'/DB_HOST = '$DB_HOST'/g" $PROJECT_DIR/config.php
    sed -i "s/DB_USERNAME   = '.*'/DB_USERNAME = '$DB_USERNAME'/g" $PROJECT_DIR/config.php
    sed -i "s/DB_PASSWORD   = '.*'/DB_PASSWORD = '$DB_PASSWORD'/g" $PROJECT_DIR/config.php
    sed -i "s/DB_NAME       = '.*'/DB_NAME = '$DB_NAME'/g" $PROJECT_DIR/config.php
    sed -i "s/url-to-easyappointments-directory/$APP_URL/g" $PROJECT_DIR/config.php

    chown -R www-data $PROJECT_DIR
}


if [ "$1" = "run" ]; then

    echo "Preparing Easy!Appointments production configuration.."

    createAppSettings

    echo "Starting Easy!Appointments production server.."

    exec docker-php-entrypoint apache2-foreground

elif [ "$1" = "dev" ]; then

    echo "Preparing Easy!Appointments development configuration.."


    createAppSettings
    sed -i "s/DEBUG_MODE    = FALSE/DEBUG_MODE    = TRUE/g" $PROJECT_DIR/config.php

    echo "Starting Easy!Appointments production server.."
    
    exec docker-php-entrypoint apache2-foreground
fi

exec $@
