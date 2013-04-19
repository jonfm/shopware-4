<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 *
 * @category   Shopware
 * @package    Shopware_Components_Model
 * @subpackage Model
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Heiner Lohaus
 * @author     $Author$
 */

namespace Shopware\Components\Model;

use Doctrine\ORM\EntityManager,
    Doctrine\ORM\ORMException,
    Doctrine\Common\EventManager,
    Doctrine\DBAL\Connection,
    Doctrine\Common\Util\Inflector,
    Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Global Manager which is responsible for initializing the adapter classes.
 *
 * {@inheritdoc}
 */
class ModelManager extends EntityManager
{
    /**
     * @var \Symfony\Component\Validator\Validator
     */
    protected $validator;

    /**
     * Creates a new EntityManager that operates on the given database connection
     * and uses the given Configuration and EventManager implementations.
     *
     * @param \Doctrine\DBAL\Connection $conn
     * @param \Shopware\Components\Model\Configuration $config
     * @param \Doctrine\Common\EventManager $eventManager
     */
    protected function __construct(Connection $conn, Configuration $config, EventManager $eventManager)
    {
        parent::__construct($conn, $config, $eventManager);
        $this->proxyFactory = new ProxyFactory(
            $this,
            $config->getProxyDir(),
            $config->getProxyNamespace(),
            $config->getAutoGenerateProxyClasses()
        );
    }

    /**
     * Factory method to create EntityManager instances.
     *
     * @param mixed $conn An array with the connection parameters or an existing
     *      Connection instance.
     * @param Configuration $config The Configuration instance to use.
     * @param \Doctrine\Common\EventManager|null $eventManager The EventManager instance to use.
     * @throws \Doctrine\ORM\ORMException
     * @return ModelManager The created EntityManager.
     */
    public static function create(Connection $conn, Configuration $config, EventManager $eventManager = null)
    {
        if (!$config->getMetadataDriverImpl()) {
            throw ORMException::missingMappingDriverImpl();
        }

        if ($eventManager !== null && $conn->getEventManager() !== $eventManager) {
            throw ORMException::mismatchedEventManager();
        }

        return new self($conn, $config, $conn->getEventManager());
    }

    /**
     * Magic method to build this liquid interface ...
     *
     * @param   string $name
     * @param   array|null $args
     * @return  ModelRepository
     */
    public function __call($name, $args)
    {
        /** @todo make path custom able */
        if (strpos($name, '\\') === false) {
            $name = $name .'\\' . $name;
        }
        $name = 'Shopware\\Models\\' . $name;
        return $this->getRepository($name);
    }

    /**
     * The EntityRepository instances.
     *
     * @var array
     */
    private $repositories = array();

    /**
     * Gets the repository for an entity class.
     *
     * @param string $entityName The name of the entity.
     * @return ModelRepository The repository class.
     */
    public function getRepository($entityName)
    {
        $entityName = ltrim($entityName, '\\');

        if (!isset($this->repositories[$entityName])) {
            $metadata = $this->getClassMetadata($entityName);
            $repositoryClassName = $metadata->customRepositoryClassName;

            if ($repositoryClassName === null) {
                $repositoryClassName = $this->getConfiguration()->getDefaultRepositoryClassName();
            }

            $repositoryClassName = $this->getConfiguration()
                ->getHookManager()->getProxy($repositoryClassName);

            $this->repositories[$entityName] = new $repositoryClassName($this, $metadata);
        }

        return $this->repositories[$entityName];
    }

    /**
     * Serialize an entity to an array
     *
     * @author      Boris Guéry <guery.b@gmail.com>
     * @license     http://sam.zoy.org/wtfpl/COPYING
     * @link        http://borisguery.github.com/bgylibrary
     * @see         https://gist.github.com/1034079#file_serializable_entity.php
     * @param       $entity
     * @return      array
     */
    protected function serializeEntity($entity)
    {
        if ($entity instanceof \Doctrine\ORM\Proxy\Proxy) {
            /** @var $entity \Doctrine\ORM\Proxy\Proxy */
            $entity->__load();
            $className = get_parent_class($entity);
        } else {
            $className = get_class($entity);
        }
        $metadata = $this->getClassMetadata($className);
        $data = array();

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $data[$field] = $metadata->reflFields[$field]->getValue($entity);
        }

        foreach ($metadata->associationMappings as $field => $mapping) {
            $key = Inflector::tableize($field);
            if ($mapping['isCascadeDetach']) {
                $data[$key] = $metadata->reflFields[$field]->getValue($entity);
                if (null !== $data[$key]) {
                    $data[$key] = $this->serializeEntity($data[$key]);
                }
            } elseif ($mapping['isOwningSide'] && $mapping['type'] & ClassMetadata::TO_ONE) {
                if (null !== $metadata->reflFields[$field]->getValue($entity)) {
                    $data[$key] = $this->getUnitOfWork()
                        ->getEntityIdentifier(
                            $metadata->reflFields[$field]
                                ->getValue($entity)
                            );
                } else {
                    // In some case the relationship may not exist, but we want
                    // to know about it
                    $data[$key] = null;
                }
            }
        }

        return $data;
    }

    /**
     * Serialize an entity or an array of entities to an array
     *
     * @param   $entity
     * @return  array
     */
    public function toArray($entity)
    {
        if ($entity instanceof \Traversable) {
           $entity = iterator_to_array($entity);
        }

        if (is_array($entity)) {
            return array_map(array($this, 'serializeEntity'), $entity);
        }

        return $this->serializeEntity($entity);
    }

    /**
     * Returns the total count of the passed query builder.
     *
     * @param \Doctrine\ORM\Query $query
     * @return int|null
     */
    public function getQueryCount(\Doctrine\ORM\Query $query)
    {
        $pagination = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $pagination->count($query);
    }

    /**
     * @return \Doctrine\ORM\QueryBuilder|QueryBuilder
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this);
    }

    /**
     * @return \Symfony\Component\Validator\Validator
     */
    public function getValidator()
    {
        if (null === $this->validator) {
            $reader = new \Doctrine\Common\Annotations\AnnotationReader;
            $this->validator = new \Symfony\Component\Validator\Validator(
                new \Symfony\Component\Validator\Mapping\ClassMetadataFactory(
                    new \Symfony\Component\Validator\Mapping\Loader\AnnotationLoader($reader)
                ),
                new \Symfony\Component\Validator\ConstraintValidatorFactory()
            );
        }
        return $this->validator;
    }

    /**
     * @param $object
     * @return \Symfony\Component\Validator\ConstraintViolationList
     */
    public function validate($object)
    {
        return $this->getValidator()->validate($object);
    }

    /**
     * @param array $tableNames
     */
    public function generateAttributeModels($tableNames = array())
    {
        $path = realpath($this->getConfiguration()->getAttributeDir()) . DIRECTORY_SEPARATOR;

        /**@var $generator \Shopware\Components\Model\Generator*/
        $generator = new \Shopware\Components\Model\Generator();

        $generator->setPath(
            $path
        );

        $generator->setModelPath(
            Shopware()->AppPath('Models')
        );

        $generator->setSchemaManager(
            $this->getOwnSchemaManager()
        );

        $generator->generateAttributeModels($tableNames);

        $this->regenerateAttributeProxies($tableNames);
    }

    /**
     * Generates Doctrine proxy classes
     *
     * @param array $tableNames
     */
    public function regenerateAttributeProxies($tableNames = array())
    {
        $metaDataCache = $this->getConfiguration()->getMetadataCacheImpl();

        if(method_exists($metaDataCache, 'deleteAll')) {
            $metaDataCache->deleteAll();
        }

        $allMetaData = $this->getMetadataFactory()->getAllMetadata();
        $proxyFactory = $this->getProxyFactory();

        $attributeMetaData = array();
        /**@var $metaData \Doctrine\ORM\Mapping\ClassMetadata*/
        foreach ($allMetaData as $metaData) {
            $tableName = $metaData->getTableName();
            if (strpos($tableName, '_attributes') === false) {
                continue;
            }
            if (!empty($tableNames) && !in_array($tableName, $tableNames)) {
                continue;
            }
            $attributeMetaData[] = $metaData;
        }
        $proxyFactory->generateProxyClasses($attributeMetaData);
    }

    /**
     * Generates Doctrine proxy classes
     */
    public function regenerateProxies()
    {
        $metadata = $this->getMetadataFactory()->getAllMetadata();
        $proxyFactory = $this->getProxyFactory();
        $proxyFactory->generateProxyClasses($metadata);
    }

    /**
     * Helper function to create an own database schema manager to remove
     * all dependencies to the existing shopware models and meta data caches.
     * @return \Doctrine\DBAL\Connection
     */
    private function getOwnSchemaManager()
    {
        /**@var $connection \Doctrine\DBAL\Connection*/
        $connection = \Doctrine\DBAL\DriverManager::getConnection(
            array('pdo' => Shopware()->Db()->getConnection())
        );

        $connection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        return $connection->getSchemaManager();
    }


    /**
     * Shopware helper function to extend an attribute table.
     *
     * @param string $table Full table name. Example: "s_user_attributes"
     * @param string $prefix Column prefix. The prefix and column parameter will be the column name. Example: "swag".
     * @param string $column The column name
     * @param string $type Full type declaration. Example: "VARCHAR( 5 )" / "DECIMAL( 10, 2 )"
     * @param bool $nullable Allow null property
     * @param null $default Default value of the column
     * @throws \InvalidArgumentException
     */
    public function addAttribute($table, $prefix, $column, $type, $nullable = true, $default = null)
    {
        if (empty($table)) {
            throw new \InvalidArgumentException('No table name passed');
        }
        if (strpos($table, '_attributes') === false) {
            throw new \InvalidArgumentException('The passed table name is no attribute table');
        }
        if (empty($prefix)) {
            throw new \InvalidArgumentException('No column prefix passed');
        }
        if (empty($column)) {
            throw new \InvalidArgumentException('No column name passed');
        }
        if (empty($type)) {
            throw new \InvalidArgumentException('No column type passed');
        }

        $name = $prefix . '_' . $column;

        if (!$this->tableExist($table)) {
            throw new \InvalidArgumentException("Table doesn't exist");
        }

        if ($this->columnExist($table, $name)) {
            return;
        }

        $null = ($nullable) ? " NULL " : " NOT NULL ";

        if (is_string($default) && strlen($default) > 0) {
            $defaultValue = "'". $default ."'";
        } elseif (is_null($default)) {
            $defaultValue = " NULL ";
        }  else {
            $defaultValue = $default;
        }

        $sql = 'ALTER TABLE ' . $table . ' ADD ' . $name . ' ' . $type . ' ' . $null . ' DEFAULT ' . $defaultValue;
        Shopware()->Db()->query($sql, array($table, $prefix, $column, $type, $null, $defaultValue));
    }

    /**
     * Shopware Helper function to remove an attribute column.
     *
     * @param $table
     * @param $prefix
     * @param $column
     * @throws \InvalidArgumentException
     */
    public function removeAttribute($table, $prefix, $column)
    {
        if (empty($table)) {
            throw new \InvalidArgumentException('No table name passed');
        }
        if (strpos($table, '_attributes') === false) {
            throw new \InvalidArgumentException('The passed table name is no attribute table');
        }
        if (empty($prefix)) {
            throw new \InvalidArgumentException('No column prefix passed');
        }
        if (empty($column)) {
            throw new \InvalidArgumentException('No column name passed');
        }

        $name = $prefix . '_' . $column;

        if (!$this->tableExist($table)) {
            throw new \InvalidArgumentException("Table doesn't exist");
        }

        if (!$this->columnExist($table, $name)) {
            return;
        }

        $sql = 'ALTER TABLE ' . $table . ' DROP ' . $name;
        Shopware()->Db()->query($sql);
    }

    /**
     * Helper function to check if the table is realy exist.
     * @param $tableName
     *
     * @return bool
     */
    private function tableExist($tableName)
    {
        $sql = "SHOW TABLES LIKE '" . $tableName . "'";
        $result = Shopware()->Db()->fetchRow($sql);
        return !empty($result);
    }

    /**
     * Internal helper function to check if a database table column exist.
     *
     * @param $tableName
     * @param $columnName
     *
     * @return bool
     */
    private function columnExist($tableName, $columnName)
    {
        $sql= "SHOW COLUMNS FROM " . $tableName . " LIKE '" . $columnName . "'";
        $result = Shopware()->Db()->fetchRow($sql);
        return !empty($result);
    }
}
