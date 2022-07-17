BACKUPDATE=`date +%F_%H-%M-%S` && echo $BACKUPDATE
docker exec site-db /usr/bin/mysqldump -u root --password=WMiJIkEjhO90 flarum | gzip > data/database-backup/$BACKUPDATE.sql.gz && echo "Database Dumped"
find data/database-backup -type f -mtime +10 -exec rm -rf {} \; 2>&1 && echo "Files older than 10 days Deleted!"
aws s3 sync flarum/public s3://flarumfs/public --delete
aws s3 sync data/database-backup s3://flarumfs/database --delete
exit 0
