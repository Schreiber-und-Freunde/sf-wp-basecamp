wp-basecamp
===========

Basecamp API Integration for Wordpress.

**Notice:** This is no complete API implementation. Only the features we need for www.schreiber-freunde.de are implemented. If you miss a feature feel free to implement it and send a pull request or file a feature request in the issues section.

## Usage
Use the wrapper function basecamp_get_todo_count() with the optional boolean parameter $open (standard value is false) to get the todo count from your basecamp account. 

* * *
**Be careful and cache the results. This plugin won't do this for you!**