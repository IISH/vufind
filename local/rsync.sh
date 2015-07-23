#!/bin/bash
#
# Syncs the PDF documents and authority databases.

datestamp=$(date +"%Y-%m-%d")
log="/data/log/rsync-${datestamp}.log"

for slave in $VUFIND_SLAVES
do
    host=$slave.iisg.net
    echo "Rsync to ${host}" >> $log
    sudo -u vufind rsync -av --progress "${VUFIND_SHARE}/solr/${HOSTNAME}/alphabetical_browse" "${host}:/${VUFIND_SHARE}/solr/${slave}/" >> $log
    sudo -u vufind rsync -av --progress "${VUFIND_CACHE_CACHE_DIR}/pdf/"*.pdf "${host}:/${VUFIND_CACHE_CACHE_DIR}/pdf" >> $log
done