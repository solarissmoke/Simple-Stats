# Simple Stats - PHP web statistics software

**Warning: this project is no longer actively maintained. Use with caution!**

Simple Stats is web statistics software written in PHP. It aims to be a simple and lightweight web statistics application that lets you analyse basic information about traffic on your website. It lets you track and filter the following:

- Page views
- Client browsers and operating Systems
- Visitor IP addresses
- Visitor origin by country
- Visitor language
- Referrers
- Search queries leading to your site 

Simple Stats is not a fully-fledged analytics application — for that you might consider Piwik.

## Installation

Unpack everything to your web server and then visit `/simple-stats/index.php` in your web browser and follow the instructions. Let me know when the automagical installation falls flat on its face :).

## Geolocation

Simple Stats supports the MaxMind geolocation database, but you will need to manually download the database (it's free) from [their website]( http://www.maxmind.com/app/geolitecountry "MaxMind Geolite Country Database") and place it in the `geoip` folder. 

Alternatively, if you already have geolocation software in place, just define the `SIMPLE_STATS_GEOIP_COUNTRY` constant in PHP before calling the hit counter, and Simple Stats will automatically use that. You should set the constant to the two-letter country code that corresponds to the country your software has identified.

## System requirements:

- PHP v5.2.4 or greater, with mbstring extension
- MySQL v5.0 or greater
