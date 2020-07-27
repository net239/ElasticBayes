<?php

namespace ElasticBayes;


use Elasticsearch\Client;

class ElasticBayes {
    /** @var  Client */
    private $client;
    private $totalDocCount = 0;
    private $labels = [];
    private $field;

    /** @var \LRUCache\LRUCache  */
    private $termLRU;

    //Index name and type
    private $index_name;
    private $index_type;

    public function __construct($labelField, $index_name = 'reuters', $index_type = 'train', $elasticHost, $elasticPort) {
        $this->client = new Client(array('hosts' => array('host' => $elasticHost, 'port' => $elasticPort)));
        $this->field = $labelField;
        $this->index_name = $index_name;
        $this->index_type = $index_type;
        $this->getLabelCounts();
        $this->termLRU = new \LRUCache\LRUCache(10000);
    }

    /**
     * Predict the label scores for a certain piece of text.  Scores are
     * sorted and normalized 1-100 by default
     */
    public function predict($data, $textField, $normalize = true) {
        $termCollection = new TermCollection($this->client, $this->termLRU, $this->labels,
                                             $this->index_name, $this->index_type);
        $termCollection->setLabelField($this->field);
        $termCollection->setTextField($textField);
        $termCollection->collectTerms($data);

        $scores = [];
        foreach ($this->labels as $label => $labelStats) {
            $scores[$label] = $termCollection->scoreLabel($label, $textField);
        }

        arsort($scores);
        return $normalize ? $this->normalize($scores) : $scores;

    }

    private function normalize($data) {
        $max = max($data);
        if ($max == 0) {
            $evenDistro = 100 / count($data);
            foreach ($data as $i => $v) {
                $data[$i] = $evenDistro;
            }
        } else {
            foreach ($data as $i => $v) {
                $data[$i] = ($v / $max) * 100;
            }
        }

        return $data;
    }

    /**
     * Before we can do anything, we need to know the distribution of label
     * counts across the whole index
     */
    private function getLabelCounts() {

        $params = [
            'index' => $this->index_name,
            'type' => $this->index_type,
            'search_type' => 'count',
            'body' => [
                'aggs' => [
                    'counts' => [
                        'terms' => [
                            'field' => $this->field,
                            'size' => 200
                        ]
                    ]
                ]
            ]
        ];

        $results = $this->client->search($params);

        $this->totalDocCount = $results['hits']['total'];
        foreach ($results['aggregations']['counts']['buckets'] as $bucket) {
            $this->labels[$bucket['key']]['count'] = $bucket['doc_count'];
            $this->labels[$bucket['key']]['prob'] = $bucket['doc_count'] / $this->totalDocCount;
        }
    }

}
