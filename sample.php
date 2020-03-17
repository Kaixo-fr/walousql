<!DOCTYPE html>
<html>
<body>
<pre>
<?php
// Include the definition of the class walousql
include 'walousql/src/walousql.inc.php';

// Create new walousql object
$walousql = new walousql();

// Point to the sampleTable table
$walousql->setTable('sampleTable');

// Insert or update two items in sampleTable table
$walousql->set(array(
    'myFirstKey' => array(
        'column_1' => 'First value 1',
        'column_2' => 'First value 2',
    ),
    'mySecondKey' => array(
        'column_1' => 'Second value 1',
        'column_2' => 'Second value 2',
    ),
));

/*
In PHP 7, you could write :

$walousql->set([
    'myFirstKey' => [
        'column_1' => 'First value 1',
        'column_2' => 'First value 2',
    ],
    'mySecondKey' => [
        'column_1' => 'Second value 1',
        'column_2' => 'Second value 2',
    ],
]);
*/

// Get all items and write them
$all = $walousql->selectAll();
var_export($all);
?>
</pre>
</body>
</html>