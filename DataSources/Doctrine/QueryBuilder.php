<?php

namespace DataGrid\DataSources\Doctrine;

use Doctrine\ORM,
	DataGrid\DataSources\Utils\WildcardHelper;

/**
 * Query Builder based data source
 * @author Michael Moravec
 * @author Štěpán Svoboda
 * @author Milan Lempera
 */
class QueryBuilder
extends \DataGrid\DataSources\Mapped
{
	const MAP_PROPERTIES = 1;
	const MAP_OBJECTS = 2;

	/** @var ORM\QueryBuilder Query builder instance */
	private $qb;

	/**
	 * The mapping type
	 * This is automaticaly detected from the SELECT statement.
	 * Supported are:
	 *		1. Mapping properties via "SELECT a.id FROM Entities\Article a"
	 *		2. Mapping objects via "SELECT a FROM Entities\Article a"
	 * @var int
	 */
	private $mappingType;

	/** @var array Fetched data */
	private $data;

	/** @var int Total data count */
	private $count;


	/**
	 * Store given query builder instance
	 * @param ORM\QueryBuilder $qb
	 */
	public function __construct(ORM\QueryBuilder $qb)
	{
		$this->qb = $qb;
	}

	public function __clone()
	{
		$this->qb=clone $this->qb;
	}

	/**
	 * Filter items in data source
	 * @param string $column
	 * @param string $operation
	 * @param string $value
	 * @param string $chainType
	 * @return QueryBuilder (fluent)
	 * @throws \Nette\InvalidArgumentException
	 */
	public function filter($column, $operation = self::EQUAL, $value = NULL, $chainType = NULL)
	{
		if (!$this->hasColumn($column)) {
			throw new \Nette\InvalidArgumentException('Trying to filter data source by unknown column.');
		}

		$nextParamId = count($this->qb->getParameters()) + 1;

		if (is_array($operation)) {
			if ($chainType !== self::CHAIN_AND && $chainType !== self::CHAIN_OR) {
				throw new \InvalidArgumentException('Invalid chain operation type.');
			}
			$conds = array();
			foreach ($operation as $t) {
				$this->validateFilterOperation($t);
				if ($t === self::IS_NULL || $t === self::IS_NOT_NULL) {
					$conds[] = "{$this->mapping[$column]} $t";
				} else {
					$conds[] = "{$this->mapping[$column]} $t ?$nextParamId";
					$this->qb->setParameter(
						$nextParamId++,
						$t === self::LIKE || $t === self::NOT_LIKE ? WildcardHelper::formatLikeStatementWildcards($value) : $value
					);
				}
			}

			if ($chainType === self::CHAIN_AND) {
				foreach ($conds as $cond) {
					$this->qb->andWhere($cond);
				}
			} elseif ($chainType === self::CHAIN_OR) {
				$this->qb->andWhere(new ORM\Query\Expr\Orx($conds));
			}
		} else {
			$this->validateFilterOperation($operation);

			if ($operation === self::IS_NULL || $operation === self::IS_NOT_NULL) {
				$this->qb->andWhere("{$this->mapping[$column]} $operation");
			} else {
				$this->qb->andWhere("{$this->mapping[$column]} $operation ?$nextParamId");
				$this->qb->setParameter(
					$nextParamId,
					$operation === self::LIKE || $operation === self::NOT_LIKE ? WildcardHelper::formatLikeStatementWildcards($value) : $value
				);
			}
		}

		return $this;
	}

	/**
	 * Sort data source
	 * @param string $column
	 * @param string $order
	 * @return QueryBuilder (fluent)
	 * @throws \Nette\InvalidArgumentException
	 */
	public function sort($column, $order = self::ASCENDING)
	{
		if (!$this->hasColumn($column)) {
			throw new \Nette\InvalidArgumentException('Trying to sort data source by unknown column.');
		}
		
		$this->qb->addOrderBy($this->mapping[$column], $order === self::ASCENDING ? 'ASC' : 'DESC');

		return $this;
	}

	/**
	 * Reduce data source to given $count starting from $start
	 * @param integer $count
	 * @param integer $start
	 * @return QueryBuilder (fluent)
	 * @throws \Nette\OutOfRangeException
	 */
	public function reduce($count, $start = 0)
	{
		if ($count == NULL || $count > 0) { //intentionally ==
			$this->qb->setMaxResults($count == NULL ? NULL : $count);
		} else {
			throw new \Nette\OutOfRangeException;
		}

		if ($start == NULL || ($start > 0 && $start < count($this))) {
			$this->qb->setFirstResult($start == NULL ? NULL : $start);
		} else {
			throw new \Nette\OutOfRangeException;
		}

		return $this;
	}

	/**
	 * Get iterator over data source items
	 * @return \ArrayIterator
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->fetch());
	}

	/**
	 * Fetches and returns the result data.
	 * @return array
	 */
	public function fetch()
	{
		$this->data = $this->qb->getQuery()->getScalarResult();

		// Detect mapping type. It will affect the hydrated column names.
		$this->detectMappingType(); 

		// Create mapping index
		$data = array();
		$i = 0;
		foreach ($this->data as & $row) {
			$data[$i] = array();
			foreach ($this->mapping as $alias => $column) {
				$data[$i][$alias] = & $row[$this->getHydratedColumnName($column)];
			}
			$i++;
		}

		return $this->data = $data;
	}

	/**
	 * Returns hydrated column name based on the mapping type.
	 * @param string $column
	 * @return string
	 */
	private function getHydratedColumnName($column)
	{
		if ($this->mappingType === self::MAP_PROPERTIES) {
			return substr($column, strpos($column, '.') !== FALSE ? strpos($column, '.') + 1 : 0);
		}
		
		if ($this->mappingType === self::MAP_OBJECTS) {
			return strtr($column, '.', '_');
		}
	}

	/**
	 * Detect the mapping type.
	 * It is detected from type of SELECT expressions.
	 */
	protected function detectMappingType()
	{
		$expressions = $this->qb->getQuery()->getAST()->selectClause->selectExpressions;
		$this->mappingType = self::MAP_PROPERTIES;
		foreach ($expressions as $expr) {
			if (is_string($expr->expression)) {
				$this->mappingType = self::MAP_OBJECTS;
			}
		}
	}

	/**
	 * Count items in data source
	 * @return int
	 */
	public function count()
	{
		$query = clone $this->qb->getQuery();
		$query->setParameters($this->qb->getQuery()->getParameters());

		$query->setHint(ORM\Query::HINT_CUSTOM_TREE_WALKERS, array(__NAMESPACE__ . '\Utils\CountingASTWalker'));
		$query->setMaxResults(NULL)->setFirstResult(NULL);

		$parts = $this->qb->getDQLParts();
		if (array_key_exists('groupBy', $parts) && count($parts['groupBy']) > 0) {
			return count($query->getScalarResult());
		}

		return (int)$query->getSingleScalarResult();
	}

	/**
	 * @param string $column
	 * @return array
	 * @throws \Nette\InvalidArgumentException
	 */
	public function getFilterItems($column)
	{
		if (!$this->hasColumn($column)) {
			throw new \Nette\InvalidArgumentException('Trying to filter data source by unknown column.');
			}

		$query=clone $this->qb->getQuery();
		$query->setParameters($this->qb->getQuery()->getParameters());
		$query->setHint(ORM\Query::HINT_CUSTOM_TREE_WALKERS, array(__NAMESPACE__.'\Utils\DistinctASTWalker'));
		$query->setMaxResults(NULL)->setFirstResult(NULL);
		$query->setHint('distinct', $this->mapping[$column]);
		if (!count($res=$query->getArrayResult())) {
			return array();
			}
		$r=array_map(
			function($row) {
				return $row[key($row)];
				},
			$res
			);
		return array_combine($r, $r);
	}
}
