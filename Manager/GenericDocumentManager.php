<?php

namespace WobbleCode\ManagerBundle\Manager;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Validator\ValidatorInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Query\Builder;
use Knp\Component\Pager\Paginator;

/**
 * @class CollectionItemManager
 */
class GenericDocumentManager
{
    /**
     * MongoDB DocumentManager
     *
     * @var DocumentManager
     */
    protected $dm;

    /**
     * EventDispatcher
     *
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * Validator
     *
     * @var ValidatorInterface
     */
    protected $paginator;

    /**
     * Key namespace used for events
     *
     * @var string key
     */
    protected $key;

    /**
     * The main object to manage
     *
     * @var string document
     */
    protected $document;

    /**
     * Define which parameters can be set from the request
     *
     * @var array
     */
    protected $acceptedFromRequest = ['page', 'query'];

    /**
     * Mapping from request, this mapping array determines what parameter should
     * get from request
     *
     * @var array
     */
    protected $mappingFromRequest = [
        'itemsPerPage' => 'per_page',
        'page'         => 'page',
        'query'        => 'q',
        'sortBy'       => 'sort_by',
        'sortDir'      => 'order'
    ];

    /**
     * Items per page to load
     *
     * @var integer
     */
    protected $itemsPerPage = 10;

    /**
     * Current page to load
     *
     * @var integer
     */
    protected $page = 1;

    /**
     * Default Sort by
     *
     * @var mixed
     */
    protected $sortBy = false;

    /**
     * Default Sort Dir
     *
     * @var mixed
     */
    protected $sortDir = false;

    /**
     * Use this fields to create search query
     *
     * @var array queryFields
     */
    protected $queryFields = [];

    /**
     * Value for filter query
     *
     * @var string query to apply
     */
    protected $query;

    /**
     * Generic Document constructor
     *
     * @param RequestStack             $requestStack    Symfony Request stack
     * @param EventDispatcherInterface $eventDispatcher Event Dispatcher
     * @param DocumentManager          $documentManager Object Manager
     * @param Paginator                $paginator       Knp Paginator
     */
    public function __construct(
        RequestStack $requestStack,
        EventDispatcherInterface $eventDispatcher,
        DocumentManager $documentManager,
        Paginator $paginator
    ) {
        $this->requestStack    = $requestStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->dm              = $documentManager;
        $this->paginator       = $paginator;
    }

    public function getDocumentManager()
    {
        return $this->dm;
    }

    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    public function setDocument($document)
    {
        $this->document = $document;

        return $this;
    }

    public function setItemsPerPage($itemsPerPage)
    {
        $this->itemsPerPage = $itemsPerPage;

        return $this;
    }

    public function setPage($page)
    {
        $this->page = $page;

        return $this;
    }

    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    public function setSortBy($sortBy)
    {
        $this->sortBy = $sortBy;

        return $this;
    }

    public function setSortDir($sortDir)
    {
        $this->sortDir = $sortDir;

        return $this;
    }

    public function setQueryFields(array $queryFields)
    {
        $this->queryFields = $queryFields;

        return $this;
    }

    public function setAcceptFromRequest(array $acceptedFromRequest)
    {
        $this->acceptedFromRequest = $acceptedFromRequest;

        return $this;
    }

    public function getDefault($parameter, $value = null)
    {
        if ($value) {
            return $value;
        }

        if (in_array($parameter, $this->acceptedFromRequest)) {
            $request = $this->requestStack->getCurrentRequest();
            $queryParameter = $this->mappingFromRequest[$parameter];

            return $request->query->get($queryParameter, $this->{$parameter});
        }

        return $this->{$parameter};
    }

    /**
     * Notify Helper Create a generic event and dispatch the event
     *
     * @param string $key       Event key name
     * @param array  $arguments Arguments with Organization included
     *
     * @example of arguments
     *
     *     $arguments = [
     *         'notifyLanguage' => 'es',
     *         'notifyUserTrigger' => $user,
     *         'notifyUsers' => [],
     *         'notifyExternal' => 'notify@email.com',
     *         'data' => [
     *             'invitation' => $invitation
     *         ]
     *     ];
     *
     * @return GenericEvent Dispatched event
     */
    public function dispatch($key, $arguments)
    {
        $event = new GenericEvent(
            $key,
            $arguments
        );

        $this->eventDispatcher->dispatch($key, $event);

        return $event;
    }


    public function addSort($qb, $field, $dir)
    {
        $field = $this->dashesToCamelCase($field);
        $qb->sort($field, $dir);

        return $qb;
    }

    public function addFilters($qb, $filters)
    {
        foreach ($filters as $filter) {
            $qb->field($filter['field'])->{$filter['selector']}($filter['value']);
        }

        return $qb;
    }

    public function addQuery($qb, $queryFields, $query)
    {
        if (!$query) {
            return $qb;
        }

        foreach ($queryFields as $field) {
            $qb->addOr($qb->expr()->field($field)->equals(new \MongoRegex('/.*'.$query.'.*/i')));
        }

        return $qb;
    }

    public function addNativeQuery($queryFields, $query, $operator = '$or')
    {
        $qb = [];

        foreach ($queryFields as $field) {
            $qb[$operator][] = [$field => new \MongoRegex('/.*'.$query.'.*/i')];
        }

        return $qb;
    }

    /**
     * Get a list documents
     *
     * @param array $filters
     *
     * List or conditional selectors http://bit.ly/1efirww
     *
     * @example filters
     *
     * [
     *     [
     *         'field' => 'id',
     *         'selector' => 'equals',
     *         'value' => 12
     *     ],
     *     …
     * ]
     *
     * @return MongoCursor
     */
    public function getDocuments(array $filters = [], $primes = [], $query = null)
    {
        $query = $this->getDefault('query', $query);
        $page = $this->getDefault('page');
        $itemsPerPage = $this->getDefault('itemsPerPage');
        $sortBy = $this->getDefault('sortBy');
        $sortDir = $this->getDefault('sortDir');

        $qb = $this->dm->createQueryBuilder($this->document);
        $qb = $this->addFilters($qb, $filters);
        $qb = $this->addQuery($qb, $this->queryFields, $query);

        foreach ($primes as $field) {
            $qb->field($field)->prime(true);
        }

        if ($sortBy) {
            $qb = $this->addSort($qb, $sortBy, $sortDir);
        }

        return $this->paginator->paginate($qb, $page, $itemsPerPage);
    }

    /**
     * Get a list documents
     *
     * @param array $filters
     *
     * List or conditional selectors http://bit.ly/1efirww
     *
     * @example filters
     *
     * [
     *     [
     *         'field' => 'id',
     *         'selector' => 'equals',
     *         'value' => 12
     *     ],
     *     …
     * ]
     *
     * @return MongoCursor
     */
    public function count(array $filters = [], $query = false)
    {
        $qb = $this->dm->createQueryBuilder($this->document);
        $qb = $this->addFilters($qb, $filters);

        if ($query) {
            $query = $this->getDefault('query', $query);
            $qb = $this->addQuery($qb, $this->queryFields, $query);
        }

        $qb->count();
        $query   = $qb->getQuery();
        $results = $query->execute();

        return $results;
    }

    /**
     * Count items by group
     *
     * @param mixed $collection
     * @param mixed $fieldGroup
     * @param mixed $match
     *
     * @return array Multidimensional array with key => count per group
     */
    public function countByGroup($fieldGroup, $match = [], $query = null, $sort = null, $limit = null)
    {
        $group = [
            '_id' => '$'.$fieldGroup,
            'count' => [ '$sum' => 1]
        ];

        return $this->aggregateGroup($group, $match, $query, $sort, $limit);
    }

    /**
     * Aggregate with custom group
     *
     * @param mixed $collection
     * @param mixed $fieldGroup
     * @param mixed $match
     *
     * @return array Multidimensional array with key => sum per item
     */
    public function aggregateGroup($group, $match = [], $query = null, $sort = null, $limit = null)
    {
        $query = $this->getDefault('query', $query);

        $collection = $this->dm->getDocumentCollection($this->document);

        $pipeline = [];

        if ($query) {
            $query = $this->addNativeQuery($this->queryFields, $query);
            $match = array_merge($match, $query);
        }


        if (count($match)) {
            $pipeline[] = [
                '$match' => $match
            ];
        }

        $pipeline[] = [
            '$group' => $group
        ];

        if ($sort) {
            $pipeline[] = [
                '$sort' => $sort
            ];
        }

        if ($limit) {
            $pipeline[] = [
                '$limit' => $limit
            ];
        }

        $return = $collection->aggregate($pipeline);

        return iterator_to_array($return);
    }

    /**
     * Find a document with support for conditions
     *
     * @param array $filters List or conditional selectors http://bit.ly/1efirww
     *
     * @example filters
     *
     * [
     *     [
     *         'field' => 'id',
     *         'selector' => 'equals',
     *         'value' => 12
     *     ],
     *     …
     * ]
     *
     * @return MongoCursor
     */
    public function find($id, array $filters = [])
    {
        $qb = $this->dm->createQueryBuilder($this->document);

        if (count($filters)) {
            $qb = $this->addFilters($qb, $filters);
        }

        $qb->field('_id')->equals(new \MongoId($id));

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * FindBy
     *
     * @param array $filters List or conditional selectors http://bit.ly/1efirww
     *
     * @return MongoCursor
     */
    public function findBy($filters = [], $sort = null)
    {
        return $this->dm->getRepository($this->document)->findBy($filters, $sort);
    }

    /**
     * FindOnBy
     *
     * @param array $filters List or conditional selectors http://bit.ly/1efirww
     *
     * @return MongoCursor
     */
    public function findOneBy($filters = [], $sort = null)
    {
        return $this->dm->getRepository($this->document)->findOneBy($filters, $sort);
    }

    /**
     * save
     *
     * @param array Array of objects or object
     */
    public function save($documents)
    {
        foreach ($documents as $document) {
            $this->dm->persist($document);
        }

        return $this->dm->flush();
    }

    /**
     * Get array of MongoIds of this objects
     *
     * @param array $objects
     *
     * @return array MongoIds array
     */
    public function getMongoIds($objects)
    {
        $ids = [];

        foreach ($objects as $object) {
            $ids[] = new \MongoId($object->getId());
        }

        return $ids;
    }

    /**
     * save
     *
     * @param Iterable Array/Iterable of objects or object
     */
    public function remove($documents)
    {
        if (!count($documents)) {
            return false;
        }

        foreach ($documents as $document) {
            $this->dm->remove($document);
        }

        return $this->dm->flush();
    }

    private function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
    {
        $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }

        return $str;
    }

    /**
     * Normalize date from ISO8601, if is string it returns a DateTime object
     *
     * @param {DateTime|string|bool} $date
     *
     * @return {DateTime|bool} Return DateTime or false for Invalid Dates
     */
    public function normalizeDate($date)
    {
        if ($date instanceof \DateTime) {
            return $date;
        }

        $date = preg_replace('/\.[0-9]+/', '', $date);
        return \DateTime::createFromFormat(\DateTime::ISO8601, $date);
    }

    /**
     * @param {DateTime|string|bool} $date
     *
     * @return {DateTime|bool} Return MongoDate or false for Invalid Dates
     */
    public function normalizeDateToMongo($date)
    {
        if ($date instanceof \DateTime) {

            return new \MongoDate($date->getTimestamp());
        }

        $date = preg_replace('/\.[0-9]+/', '', $date);
        $date = \DateTime::createFromFormat(\DateTime::ISO8601, $date);

        return new \MongoDate($date->getTimestamp());
    }
}
