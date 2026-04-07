#!/bin/bash

# Laravel Scheduler Cron Setup Script
# This script sets up the Laravel scheduler to run every minute

PROJECT_PATH="/home/chetaru/workspace/tribe-community"
CRON_LOG="$PROJECT_PATH/storage/logs/cron.log"
CRON_ENTRY="* * * * * cd $PROJECT_PATH && php artisan schedule:run >> $CRON_LOG 2>&1"

echo "Setting up Laravel Scheduler cron job..."
echo "Project path: $PROJECT_PATH"
echo ""

# Check if cron entry already exists
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    echo "⚠️  Cron entry already exists. Current crontab:"
    crontab -l
    echo ""
    read -p "Do you want to replace it? (y/n): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Cancelled."
        exit 1
    fi
    # Remove existing schedule:run entry
    crontab -l 2>/dev/null | grep -v "schedule:run" | crontab -
fi

# Add the cron entry
(crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -

echo "✅ Cron job added successfully!"
echo ""
echo "Current crontab:"
crontab -l
echo ""
echo "The scheduler will run every minute and log to: $CRON_LOG"
echo ""
echo "To test immediately, run:"
echo "  cd $PROJECT_PATH && php artisan schedule:run"
echo ""
echo "To view cron logs:"
echo "  tail -f $CRON_LOG"
echo ""
echo "To view Laravel logs:"
echo "  tail -f $PROJECT_PATH/storage/logs/laravel.log"

