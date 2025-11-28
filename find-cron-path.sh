#!/bin/bash

# Script to find the correct path for CloudPanel cron job setup

echo "=== Finding Laravel Project Path for CloudPanel Cron ==="
echo ""

# Current directory
CURRENT_DIR=$(pwd)
echo "Current directory: $CURRENT_DIR"
echo ""

# Check if artisan exists here
if [ -f "artisan" ]; then
    echo "✅ Found artisan file in current directory"
    echo "Project path: $CURRENT_DIR"
    echo ""
    echo "PHP path:"
    which php
    echo ""
    echo "=== CloudPanel Cron Command ==="
    echo ""
    echo "Name: Laravel Scheduler"
    echo "Schedule: * * * * *"
    echo "Command:"
    echo "cd $CURRENT_DIR && $(which php) artisan schedule:run >> $CURRENT_DIR/storage/logs/cron.log 2>&1"
    echo ""
else
    echo "❌ artisan file not found in current directory"
    echo ""
    echo "Searching for Laravel projects..."
    echo ""
    
    # Search in common locations
    for dir in /home/tribe365-community/community.tribe365.co \
               /home/tribe365-community/public_html \
               /home/tribe365-community/htdocs \
               /var/www/community.tribe365.co; do
        if [ -f "$dir/artisan" ]; then
            echo "✅ Found Laravel project at: $dir"
            echo ""
            echo "=== CloudPanel Cron Command ==="
            echo ""
            echo "Name: Laravel Scheduler"
            echo "Schedule: * * * * *"
            echo "Command:"
            echo "cd $dir && $(which php) artisan schedule:run >> $dir/storage/logs/cron.log 2>&1"
            echo ""
            exit 0
        fi
    done
    
    echo "Could not find Laravel project automatically."
    echo "Please navigate to your Laravel project directory and run this script again."
fi

