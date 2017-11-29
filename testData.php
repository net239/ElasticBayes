<?php
require 'vendor/autoload.php';


if ( $argc != 6)
{
    printf("Need all arguments, found only %d\n",$argc);
    printf("Usage:testProductInquiries.php '%s' '%s' '%s' '%s' '%s'\n", 
            "Column name for label (e.g. SPAM_FLAG)" , "index name" , "index type" , "column name for text", "text to be tested");
    return -1;
}

$columnNameForLabel=$argv[1];
$IndexName=$argv[2];
$IndexType=$argv[3];
$columnNameForText=$argv[4];
$text=$argv[5];

printf("Starting.. columnNameForLabel:$columnNameForLabel IndexName:$IndexName IndexType:$IndexType columnNameForText:$columnNameForText\n\n");

$client = new \Elasticsearch\Client();
$bayes = new \ElasticBayes\ElasticBayes($columnNameForLabel,$IndexName,$IndexType);

$testTexts[] = $text;

$i = 0;
foreach ($testTexts as $testText )
{
    ++$i;
    $scores = $bayes->predict($testText, $columnNameForText);

    foreach ( $scores as $flag => $score )
    {
        printf("%3d: %s... Probability: %s : %s\n" , $i , substr($testText,0,32), $flag,$score);
    }
}
