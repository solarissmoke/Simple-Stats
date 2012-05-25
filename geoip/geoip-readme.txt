Simple Stats uses the Maxmind GeoLite Country database for Geolocation. 

The database itself is not included in this repository (although it is included in the packaged zip download), but can be downloaded freely from http://www.maxmind.com/app/geolitecountry. The database is updated roughly once a month. 

Place the GeoIP.dat file in this directory to enable Geolocation in Simple Stats.

Alternatively, if you want to supply your own geolocation information, just define the PHP constant SIMPLE_STATS_GEOIP_COUNTRY with a two-letter country code, and Simple Stats will use that instead.