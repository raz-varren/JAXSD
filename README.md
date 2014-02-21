JAXSD
=====

JSON Schema to XSD converter

I had been making a lot of APIs and validating my user input with JSON schema. It was all going pretty well until I had a client that could only send me data in XML format. I really didn't want to write out a gross XSD for each one of my nice clean JSON schema files, and after spending who knows how many hours trying to find a script to do it for me, I came to the realization that such a script did not exist. So, I decided to make my own. I realize that there is a lot that gets lost in the translation from JSON to XSD, but if all you need is basic JSON to XSD conversion, this script will do the trick. It has certainly saved me a lot of time.

### Example:

```php
<?php

include('jaxsd.php');

$schema_file = '/path/to/json/schema.json';

$schema_data = json_decode(file_get_contents($schema_file));

echo Jaxsd::convert($schema_data, true);

exit;
```