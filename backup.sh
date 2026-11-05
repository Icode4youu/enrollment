#!/bin/bash
# backup.sh — nightly backup for seait_enrollment (Problem 17: recovery)
# Keeps the last 7 compressed dumps; schedule with cron:
#   0 2 * * * /home/markdulaydanila/Downloads/enrollment/backup.sh

DB="seait_enrollment"
SOCK="/opt/lampp/var/mysql/mysql.sock"
DEST="$(dirname "$0")/backups"
KEEP=7

mkdir -p "$DEST"
FILE="$DEST/${DB}_$(date +%Y-%m-%d_%H-%M-%S).sql.gz"

/opt/lampp/bin/mysqldump --socket="$SOCK" --skip-ssl -u root "$DB" | gzip > "$FILE"

if [ -s "$FILE" ]; then
    echo "$(date '+%F %T') backup OK -> $FILE ($(du -h "$FILE" | cut -f1))"
    # rotate: keep only the newest $KEEP backups
    ls -t "$DEST"/${DB}_*.sql.gz | tail -n +$((KEEP+1)) | xargs -r rm --
else
    echo "$(date '+%F %T') backup FAILED" >&2
    rm -f "$FILE"
    exit 1
fi
