#!/bin/bash
#
# Syncs the PDF documents and authority databases.

for slave in $VUFIND_SLAVES
do
    host=$slave.iisg.net
    sudo -u vufind rsync -v --progress "${VUFIND_SHARE}/solr/${HOSTNAME}/alphabetical_browse/" "${host}:/${VUFIND_SHARE}/solr/${slave}/alphabetical_browse"
    sudo -u vufind rsync -v --progress "${VUFIND_CACHE_CACHE_DIR}/pdf/*.pdf" "${host}:/${VUFIND_CACHE_CACHE_DIR}/pdf"

done
