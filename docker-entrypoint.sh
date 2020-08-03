#!/usr/bin/env sh

createAppSettings() {
    cp $PROJECT_DIR/config-sample.php $PROJECT_DIR/config.php
    sed -i "s/DB_HOST       = ''/DB_HOST = '$DB_HOST'/g" $PROJECT_DIR/config.php
    sed -i "s/DB_USERNAME   = ''/DB_USERNAME = '$DB_USERNAME'/g" $PROJECT_DIR/config.php
    sed -i "s/DB_PASSWORD   = ''/DB_PASSWORD = '$DB_PASSWORD'/g" $PROJECT_DIR/config.php
    sed -i "s/DB_NAME       = ''/DB_NAME = '$DB_NAME'/g" $PROJECT_DIR/config.php
    if [ "$MAIL_PROTOCOL" = "smtp" ]; then
        echo "Setting up email..."
        sed -i "s/MAIL_PROTOCOL       = 'mail'/MAIL_PROTOCOL = '$MAIL_PROTOCOL'/g" $PROJECT_DIR/config.php
        sed -i "s/MAIL_SMTP_HOST       = ''/MAIL_SMTP_HOST = '$MAIL_SMTP_HOST'/g" $PROJECT_DIR/config.php
        sed -i "s/MAIL_SMTP_USER       = ''/MAIL_SMTP_USER = '$MAIL_SMTP_USER'/g" $PROJECT_DIR/config.php
        sed -i "s/MAIL_SMTP_PASS       = ''/MAIL_SMTP_PASS = '$MAIL_SMTP_PASS'/g" $PROJECT_DIR/config.php
    fi
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
