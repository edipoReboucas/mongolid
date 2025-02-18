<?php

namespace Mongolid\DataMapper;

use InvalidArgumentException;
use Mockery as m;
use MongoDB\BSON\ObjectID;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\WriteConcern;
use Mongolid\Connection\Connection;
use Mongolid\Container\Container;
use Mongolid\Cursor\SchemaCacheableCursor;
use Mongolid\Cursor\SchemaCursor;
use Mongolid\Event\EventTriggerService;
use Mongolid\Model\ModelInterface;
use Mongolid\Schema\Schema;
use stdClass;
use Mongolid\TestCase;

class DataMapperTest extends TestCase
{
    /**
     * @var m\MockInterface|EventTriggerService
     */
    protected $eventService;

    public function tearDown(): void
    {
        unset($this->eventService);
        parent::tearDown();
        m::close();
    }

    /**
     * @dataProvider getWriteConcernVariations
     */
    public function testShouldSave($entity, $writeConcern, $shouldFireEventAfter, $expected)
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[parseToDocument,getCollection]', [$connection]);
        $options = ['writeConcern' => new WriteConcern($writeConcern)];

        $collection = m::mock(Collection::class);
        $parsedObject = ['_id' => 123];
        $operationResult = m::mock();

        $entity->_id = null;

        // Act
        $mapper->shouldAllowMockingProtectedMethods();

        $mapper->shouldReceive('parseToDocument')
            ->once()
            ->with($entity)
            ->andReturn($parsedObject);

        $mapper->shouldReceive('getCollection')
            ->once()
            ->andReturn($collection);

        $collection->shouldReceive('replaceOne')
            ->once()
            ->with(
                ['_id' => 123],
                $parsedObject,
                ['upsert' => true, 'writeConcern' => new WriteConcern($writeConcern)]
            )->andReturn($operationResult);

        $operationResult->shouldReceive('isAcknowledged')
            ->once()
            ->andReturn((bool) $writeConcern);

        $operationResult->shouldReceive('getModifiedCount', 'getUpsertedCount')
            ->andReturn(1);

        if ($entity instanceof ModelInterface) {
            $entity->shouldReceive('syncOriginalDocumentAttributes')
                ->once()
                ->with();
        }

        $this->expectEventToBeFired('saving', $entity, true);

        if ($shouldFireEventAfter) {
            $this->expectEventToBeFired('saved', $entity, false);
        } else {
            $this->expectEventNotToBeFired('saved', $entity);
        }

        // Assert
        $this->assertEquals($expected, $mapper->save($entity, $options));
    }

    /**
     * @dataProvider getWriteConcernVariations
     */
    public function testShouldInsert($entity, $writeConcern, $shouldFireEventAfter, $expected)
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[parseToDocument,getCollection]', [$connection]);
        $options = ['writeConcern' => new WriteConcern($writeConcern)];

        $collection = m::mock(Collection::class);
        $parsedObject = ['_id' => 123];
        $operationResult = m::mock();

        $entity->_id = null;

        // Act
        $mapper->shouldAllowMockingProtectedMethods();

        $mapper->shouldReceive('parseToDocument')
            ->once()
            ->with($entity)
            ->andReturn($parsedObject);

        $mapper->shouldReceive('getCollection')
            ->once()
            ->andReturn($collection);

        $collection->shouldReceive('insertOne')
            ->once()
            ->with($parsedObject, ['writeConcern' => new WriteConcern($writeConcern)])
            ->andReturn($operationResult);

        $operationResult->shouldReceive('isAcknowledged')
            ->once()
            ->andReturn((bool) $writeConcern);

        $operationResult->shouldReceive('getInsertedCount')
            ->andReturn(1);

        if ($entity instanceof ModelInterface) {
            $entity->shouldReceive('syncOriginalDocumentAttributes')
                ->once()
                ->with();
        }

        $this->expectEventToBeFired('inserting', $entity, true);

        if ($shouldFireEventAfter) {
            $this->expectEventToBeFired('inserted', $entity, false);
        } else {
            $this->expectEventNotToBeFired('inserted', $entity);
        }

        // Assert
        $this->assertEquals($expected, $mapper->insert($entity, $options));
    }

    /**
     * @dataProvider getWriteConcernVariations
     */
    public function testShouldInsertWithoutFiringEvents($entity, $writeConcern, $shouldFireEventAfter, $expected)
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[parseToDocument,getCollection]', [$connection]);
        $options = ['writeConcern' => new WriteConcern($writeConcern)];

        $collection = m::mock(Collection::class);
        $parsedObject = ['_id' => 123];
        $operationResult = m::mock();

        $entity->_id = null;

        // Act
        $mapper->shouldAllowMockingProtectedMethods();

        $mapper->shouldReceive('parseToDocument')
            ->once()
            ->with($entity)
            ->andReturn($parsedObject);

        $mapper->shouldReceive('getCollection')
            ->once()
            ->andReturn($collection);

        $collection->shouldReceive('insertOne')
            ->once()
            ->with($parsedObject, ['writeConcern' => new WriteConcern($writeConcern)])
            ->andReturn($operationResult);

        $operationResult->shouldReceive('isAcknowledged')
            ->once()
            ->andReturn((bool) $writeConcern);

        $operationResult->shouldReceive('getInsertedCount')
            ->andReturn(1);

        if ($entity instanceof ModelInterface) {
            $entity->shouldReceive('syncOriginalDocumentAttributes')
                ->once()
                ->with();
        }

        $this->expectEventNotToBeFired('inserting', $entity);
        $this->expectEventNotToBeFired('inserted', $entity);

        // Assert
        $this->assertEquals($expected, $mapper->insert($entity, $options, false));
    }

    /**
     * @dataProvider getWriteConcernVariations
     */
    public function testShouldUpdate($entity, $writeConcern, $shouldFireEventAfter, $expected)
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[parseToDocument,getCollection]', [$connection]);

        $collection = m::mock(Collection::class);
        $parsedObject = ['_id' => 123];
        $operationResult = m::mock();
        $options = ['writeConcern' => new WriteConcern($writeConcern)];

        $entity->_id = 123;

        // Act
        $mapper->shouldAllowMockingProtectedMethods();

        $mapper->shouldReceive('parseToDocument')
            ->once()
            ->with($entity)
            ->andReturn($parsedObject);

        $mapper->shouldReceive('getCollection')
            ->once()
            ->andReturn($collection);

        $collection->shouldReceive('updateOne')
            ->once()
            ->with(
                ['_id' => 123],
                ['$set' => $parsedObject],
                ['writeConcern' => new WriteConcern($writeConcern)]
            )->andReturn($operationResult);

        $operationResult->shouldReceive('isAcknowledged')
            ->once()
            ->andReturn((bool) $writeConcern);

        $operationResult->shouldReceive('getModifiedCount')
            ->andReturn(1);

        if ($entity instanceof ModelInterface) {
            $entity->shouldReceive('getOriginalDocumentAttributes')
                ->once()
                ->with()
                ->andReturn([]);

            $entity->shouldReceive('syncOriginalDocumentAttributes')
                ->once()
                ->with();
        }

        $this->expectEventToBeFired('updating', $entity, true);

        if ($shouldFireEventAfter) {
            $this->expectEventToBeFired('updated', $entity, false);
        } else {
            $this->expectEventNotToBeFired('updated', $entity);
        }

        // Assert
        $this->assertEquals($expected, $mapper->update($entity, $options));
    }

    public function testDifferentialUpdateShouldWork()
    {
        // Arrange
        $entity = m::mock(ModelInterface::class);
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[parseToDocument,getCollection]', [$connection]);

        $collection = m::mock(Collection::class);
        $operationResult = m::mock();
        $options = ['writeConcern' => new WriteConcern(1)];

        $entity->_id = 123;
        $parsedObject = [
            '_id' => 123,
            'name' => 'Original Name',
            'age' => 32,
            'hobbies' => ['bike', 'skate', 'gardening'],
            'address' => null,
            'other' => null,
            'nested' => [['field' => null], ['other-field' => 'other-value']],
            'data' => [['key' => '123'], null, ['other-field' => 'other-value']],
            'array-to-object' => ['first' => 'value', 'second' => 'other-value'],
        ];
        $originalDocumentAttributes = [
            '_id' => 123,
            'name' => 'Original Name',
            'hobbies' => ['bike', 'motorcycle', 'gardening'],
            'address' => '1 Blue street',
            'gender' => 'm',
            'nullField' => null,
            'nested' => [['field' => 'value'], null],
            'data' => [],
            'array-to-object' => [],
        ];
        $updateData = [
            '$set' => [
                'age' => 32,
                'hobbies.1' => 'skate',
                'nested.1' => ['other-field' => 'other-value'],
                'data' => [['key' => '123'], ['other-field' => 'other-value']],
                'array-to-object' => ['first' => 'value', 'second' => 'other-value'],
            ],
            '$unset' => [
                'nested.0.field' => '',
                'address' => '',
                'gender' => '',
                'nullField' => '',
            ],
        ];

        // Act
        $mapper->shouldAllowMockingProtectedMethods();

        $mapper->shouldReceive('parseToDocument')
            ->once()
            ->with($entity)
            ->andReturn($parsedObject);

        $mapper->shouldReceive('getCollection')
            ->once()
            ->andReturn($collection);

        $collection->shouldReceive('updateOne')
            ->once()
            ->with(
                ['_id' => 123],
                $updateData,
                $options
            )->andReturn($operationResult);

        $operationResult->shouldReceive('isAcknowledged')
            ->once()
            ->andReturn(true);

        $operationResult->shouldReceive('getModifiedCount')
            ->andReturn(1);

        $entity->shouldReceive('getOriginalDocumentAttributes')
            ->once()
            ->with()
            ->andReturn($originalDocumentAttributes);

        $entity->shouldReceive('syncOriginalDocumentAttributes')
            ->once()
            ->with();

        $this->expectEventToBeFired('updating', $entity, true);
        $this->expectEventToBeFired('updated', $entity, false);

        // Assert
        $this->assertTrue($mapper->update($entity, $options));
    }

    public function testDifferentialUpdateShouldWorkHandlingNullValuesInArrays()
    {
        // Arrange
        $entity = m::mock(ModelInterface::class);
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[parseToDocument,getCollection]', [$connection]);

        $collection = m::mock(Collection::class);
        $operationResult = m::mock();
        $options = ['writeConcern' => new WriteConcern(1)];

        $entity->_id = 123;
        $parsedObject = [
            '_id' => 123,
            'name' => 'Original Name',
            'age' => 32,
            'hobbies' => ['bike', 'skate'],
            'address' => null,
            'other' => null,
            'data' => [['key' => '123'], null, ['other-field' => 'other-value']],
            'array-to-object' => ['first' => 'value', 'second' => 'other-value'],
            'nested' => [
                'field' => 'value',
                'null-values' => [null, null],
            ],
        ];
        $originalDocumentAttributes = [
            '_id' => 123,
            'name' => 'Original Name',
            'hobbies' => ['bike', 'motorcycle', 'gardening'],
            'address' => '1 Blue street',
            'gender' => 'm',
            'nullField' => null,
            'data' => [],
            'array-to-object' => [],
            'nested' => [
                'field' => 'value',
                'null-values' => [[1, 2, 3], null],
            ],
        ];
        $firstUpdateData = [
            '$set' => [
                'age' => 32,
                'hobbies.1' => 'skate',
                'data' => [['key' => '123'], ['other-field' => 'other-value']],
                'array-to-object' => ['first' => 'value', 'second' => 'other-value'],
            ],
            '$unset' => [
                'hobbies.2' => '',
                'address' => '',
                'gender' => '',
                'nullField' => '',
                'nested.null-values.0' => '',
                'nested.null-values.1' => '',
            ],
        ];

        $secondUpdateData = [
            '$pull' => [
                'hobbies' => null,
                'nested.null-values' => null,
            ]
        ];

        // Act
        $mapper->shouldAllowMockingProtectedMethods();

        $mapper->shouldReceive('parseToDocument')
            ->once()
            ->with($entity)
            ->andReturn($parsedObject);

        $mapper->shouldReceive('getCollection')
            ->once()
            ->andReturn($collection);

        $collection->shouldReceive('updateOne')
            ->once()
            ->with(
                ['_id' => 123],
                $firstUpdateData,
                $options
            )->andReturn($operationResult);

        $collection->shouldReceive('updateOne')
            ->once()
            ->with(
                ['_id' => 123],
                $secondUpdateData,
                $options
            );

        $operationResult->shouldReceive('isAcknowledged')
            ->once()
            ->andReturn(true);

        $operationResult->shouldReceive('getModifiedCount')
            ->andReturn(1);

        $entity->shouldReceive('getOriginalDocumentAttributes')
            ->once()
            ->with()
            ->andReturn($originalDocumentAttributes);

        $entity->shouldReceive('syncOriginalDocumentAttributes')
            ->once()
            ->with();

        $this->expectEventToBeFired('updating', $entity, true);
        $this->expectEventToBeFired('updated', $entity, false);

        // Assert
        $this->assertTrue($mapper->update($entity, $options));
    }

    public function testDifferentialUpdateShouldReturnTrueIfThereIsNothingToUpdate()
    {
        // Arrange
        $entity = m::mock(ModelInterface::class);
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[parseToDocument,getCollection]', [$connection]);

        $options = ['writeConcern' => new WriteConcern(1)];

        $entity->_id = 123;
        $parsedObject = [
            '_id' => 123,
            'name' => 'Original Name',
            'age' => 32,
            'hobbies' => ['bike', 'skate'],
            'nested' => [['field' => null]],
            'data' => ['key' => '123'],
        ];
        $originalDocumentAttributes = $parsedObject;

        // Act
        $mapper->shouldAllowMockingProtectedMethods();

        $mapper->shouldReceive('parseToDocument')
            ->once()
            ->with($entity)
            ->andReturn($parsedObject);

        $mapper->shouldReceive('getCollection')
            ->never();

        $entity->shouldReceive('getOriginalDocumentAttributes')
            ->once()
            ->with()
            ->andReturn($originalDocumentAttributes);

        $this->expectEventToBeFired('updating', $entity, true);
        $this->expectEventNotToBeFired('updated', $entity);

        // Assert
        $this->assertTrue($mapper->update($entity, $options));
    }

    /**
     * @dataProvider getWriteConcernVariations
     */
    public function testUpdateShouldCallInsertWhenObjectHasNoId(
        $entity,
        $writeConcern,
        $shouldFireEventAfter,
        $expected
    ) {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[parseToDocument,getCollection]', [$connection]);

        $collection = m::mock(Collection::class);
        $parsedObject = ['_id' => 123];
        $operationResult = m::mock();
        $options = ['writeConcern' => new WriteConcern($writeConcern)];

        $entity->_id = null;

        // Act
        $mapper->shouldAllowMockingProtectedMethods();

        $mapper->shouldReceive('parseToDocument')
            ->once()
            ->with($entity)
            ->andReturn($parsedObject);

        $mapper->shouldReceive('getCollection')
            ->once()
            ->andReturn($collection);

        $collection->shouldReceive('insertOne')
            ->once()
            ->with(
                $parsedObject,
                ['writeConcern' => new WriteConcern($writeConcern)]
            )->andReturn($operationResult);

        $operationResult->shouldReceive('isAcknowledged')
            ->once()
            ->andReturn((bool) $writeConcern);

        $operationResult->shouldReceive('getInsertedCount')
            ->andReturn(1);

        if ($entity instanceof ModelInterface) {
            $entity->shouldReceive('syncOriginalDocumentAttributes')
                ->once()
                ->with();
        }

        $this->expectEventToBeFired('updating', $entity, true);

        if ($shouldFireEventAfter) {
            $this->expectEventToBeFired('updated', $entity, false);
        } else {
            $this->expectEventNotToBeFired('updated', $entity);
        }

        $this->expectEventNotToBeFired('inserting', $entity);
        $this->expectEventNotToBeFired('inserted', $entity);

        // Assert
        $this->assertEquals($expected, $mapper->update($entity, $options));
    }

    /**
     * @dataProvider getWriteConcernVariations
     */
    public function testShouldDelete($entity, $writeConcern, $shouldFireEventAfter, $expected)
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[parseToDocument,getCollection]', [$connection]);

        $collection = m::mock(Collection::class);
        $parsedObject = ['_id' => 123];
        $operationResult = m::mock();
        $options = ['writeConcern' => new WriteConcern($writeConcern)];

        $entity->_id = null;

        // Act
        $mapper->shouldAllowMockingProtectedMethods();

        $mapper->shouldReceive('parseToDocument')
            ->once()
            ->with($entity)
            ->andReturn($parsedObject);

        $mapper->shouldReceive('getCollection')
            ->once()
            ->andReturn($collection);

        $collection->shouldReceive('deleteOne')
            ->once()
            ->with(['_id' => 123], ['writeConcern' => new WriteConcern($writeConcern)])
            ->andReturn($operationResult);

        $operationResult->shouldReceive('isAcknowledged')
            ->once()
            ->andReturn((bool) $writeConcern);

        $operationResult->shouldReceive('getDeletedCount')
            ->andReturn(1);

        if ($entity instanceof ModelInterface) {
            $entity->shouldReceive('syncOriginalDocumentAttributes')
                ->once()
                ->with();
        }

        $this->expectEventToBeFired('deleting', $entity, true);

        if ($shouldFireEventAfter) {
            $this->expectEventToBeFired('deleted', $entity, false);
        } else {
            $this->expectEventNotToBeFired('deleted', $entity);
        }

        // Assert
        $this->assertEquals($expected, $mapper->delete($entity, $options));
    }

    /**
     * @dataProvider eventsToBailOperations
     */
    public function testDatabaseOperationsShouldBailOutIfTheEventHandlerReturnsFalse(
        $operation,
        $dbOperation,
        $eventName
    ) {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[parseToDocument,getCollection]', [$connection]);
        $collection = m::mock(Collection::class);
        $entity = m::mock();

        $mapper->shouldAllowMockingProtectedMethods();

        // Expect
        $mapper->shouldReceive('parseToDocument')
            ->with($entity)
            ->never();

        $mapper->shouldReceive('getCollection')
            ->andReturn($collection);

        $collection->shouldReceive($dbOperation)
            ->never();

        /* "Mocks" the fireEvent to return false and bail the operation */
        $this->expectEventToBeFired($eventName, $entity, true, false);

        // Act
        $result = $mapper->$operation($entity);

        // Assert
        $this->assertFalse($result);
    }

    public function testShouldGetWithWhereQuery()
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[prepareValueQuery,getCollection]', [$connection]);
        $schema = m::mock(Schema::class);

        $collection = m::mock(Collection::class);
        $query = 123;
        $preparedQuery = ['_id' => 123];
        $projection = ['project' => true, '_id' => false];

        $schema->entityClass = 'stdClass';
        $mapper->setSchema($schema);

        $mapper->shouldAllowMockingProtectedMethods();

        // Expect
        $mapper->shouldReceive('prepareValueQuery')
            ->with($query)
            ->andReturn($preparedQuery);

        $mapper->shouldReceive('getCollection')
            ->andReturn($collection);

        // Act
        $result = $mapper->where($query, $projection);
        $cacheableResult = $mapper->where($query, [], true);

        // Assert
        $this->assertInstanceOf(SchemaCursor::class, $result);
        $this->assertNotInstanceOf(SchemaCacheableCursor::class, $result);
        $this->assertEquals($schema, $result->entitySchema);

        $this->assertInstanceOf(SchemaCacheableCursor::class, $cacheableResult);
        $this->assertEquals($schema, $cacheableResult->entitySchema);
    }

    public function testShouldGetAll()
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[where]', [$connection]);
        $mongolidCursor = m::mock(SchemaCursor::class);

        // Expect
        $mapper->shouldReceive('where')
            ->once()
            ->with([])
            ->andReturn($mongolidCursor);

        // Act
        $result = $mapper->all();

        // Assert
        $this->assertEquals($mongolidCursor, $result);
    }

    public function testShouldGetNullIfFirstCantFindAnything()
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[prepareValueQuery,getCollection]', [$connection]);
        $schema = m::mock(Schema::class);

        $collection = m::mock(Collection::class);
        $query = 123;
        $preparedQuery = ['_id' => 123];

        $schema->entityClass = 'stdClass';
        $mapper->setSchema($schema);

        $mapper->shouldAllowMockingProtectedMethods();

        // Expect
        $mapper->shouldReceive('prepareValueQuery')
            ->once()
            ->with($query)
            ->andReturn($preparedQuery);

        $mapper->shouldReceive('getCollection')
            ->once()
            ->andReturn($collection);

        $collection->shouldReceive('findOne')
            ->once()
            ->with($preparedQuery, ['projection' => []])
            ->andReturn(null);

        // Act
        $result = $mapper->first($query);

        // Assert
        $this->assertNull($result);
    }

    public function testShouldGetFirstProjectingFields()
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(
            DataMapper::class.'[prepareValueQuery,getCollection]',
            [$connection]
        );
        $schema = m::mock(Schema::class);

        $collection = m::mock(Collection::class);
        $query = 123;
        $preparedQuery = ['_id' => 123];
        $projection = ['project' => true, 'fields' => false];

        $schema->entityClass = 'stdClass';
        $mapper->setSchema($schema);

        $mapper->shouldAllowMockingProtectedMethods();

        // Expect
        $mapper->shouldReceive('prepareValueQuery')
            ->once()
            ->with($query)
            ->andReturn($preparedQuery);

        $mapper->shouldReceive('getCollection')
            ->once()
            ->andReturn($collection);

        $collection->shouldReceive('findOne')
            ->once()
            ->with($preparedQuery, ['projection' => $projection])
            ->andReturn(null);

        // Act
        $result = $mapper->first($query, $projection);

        // Assert
        $this->assertNull($result);
    }

    public function testShouldGetFirstTroughACacheableCursor()
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[where]', [$connection]);
        $query = 123;
        $entity = new stdClass();
        $cursor = m::mock(SchemaCacheableCursor::class);

        // Expect
        $mapper->shouldReceive('where')
            ->once()
            ->with($query, [], true)
            ->andReturn($cursor);

        $cursor->shouldReceive('first')
            ->once()
            ->andReturn($entity);

        // Act
        $result = $mapper->first($query, [], true);

        // Assert
        $this->assertEquals($entity, $result);
    }

    public function testShouldGetFirstTroughACacheableCursorProjectingFields()
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[where]', [$connection]);
        $query = 123;
        $entity = new stdClass();
        $cursor = m::mock(SchemaCacheableCursor::class);
        $projection = ['project' => true, '_id' => false];

        // Expect
        $mapper->shouldReceive('where')
            ->once()
            ->with($query, $projection, true)
            ->andReturn($cursor);

        $cursor->shouldReceive('first')
            ->once()
            ->andReturn($entity);

        // Act
        $result = $mapper->first($query, $projection, true);

        // Assert
        $this->assertEquals($entity, $result);
    }

    public function testShouldParseObjectToDocumentAndPutResultingIdIntoTheGivenObject()
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = m::mock(DataMapper::class.'[getSchemaMapper]', [$connection]);
        $entity = m::mock();
        $parsedDocument = ['a_field' => 123, '_id' => 'bacon'];
        $schemaMapper = m::mock(Schema::class.'[]');

        $mapper->shouldAllowMockingProtectedMethods();

        // Expect
        $mapper->shouldReceive('getSchemaMapper')
            ->once()
            ->andReturn($schemaMapper);

        $schemaMapper->shouldReceive('map')
            ->once()
            ->with($entity)
            ->andReturn($parsedDocument);

        // Act
        $result = $this->callProtected($mapper, 'parseToDocument', [$entity]);

        // Assert
        $this->assertEquals($parsedDocument, $result);
        $this->assertEquals(
            'bacon', // Since this was the parsedDocument _id
            $entity->_id
        );
    }

    public function testShouldGetSchemaMapper()
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = new DataMapper($connection);
        $mapper->schemaClass = 'MySchema';
        $schema = m::mock(Schema::class);

        Container::instance('MySchema', $schema);

        // Act
        $result = $this->callProtected($mapper, 'getSchemaMapper');

        // Assert
        $this->assertInstanceOf(SchemaMapper::class, $result);
        $this->assertEquals($schema, $result->schema);
    }

    public function testShouldGetRawCollection()
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = new DataMapper($connection);
        $collection = m::mock(Collection::class);
        $client = m::mock(Client::class);
        $database = m::mock(Database::class);
        $schema = m::mock(Schema::class);
        $schema->collection = 'foobar';

        $mapper->setSchema($schema);
        $connection->defaultDatabase = 'grimory';
        $connection->grimory = (object) ['foobar' => $collection];

        // Expect
        $connection->shouldReceive('getClient')
            ->once()
            ->andReturn($client);

        $client->shouldReceive('selectDatabase')
            ->andReturn($database);

        $database->shouldReceive('selectCollection')
            ->andReturn($collection);

        // Act
        $result = $this->callProtected($mapper, 'getCollection');

        // Assert
        $this->assertEquals($collection, $result);
    }

    /**
     * @dataProvider queryValueScenarios
     */
    public function testShouldPrepareQueryValue($value, $expectation)
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = new DataMapper($connection);

        // Act
        $result = $this->callProtected($mapper, 'prepareValueQuery', [$value]);

        // Assert
        $this->assertMongoQueryEquals($expectation, $result);
    }

    /**
     * @dataProvider getProjections
     */
    public function testPrepareProjectionShouldConvertArray($data, $expectation)
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = new DataMapper($connection);

        // Act
        $result = $this->callProtected($mapper, 'prepareProjection', [$data]);

        // Assert
        $this->assertEquals($expectation, $result);
    }

    public function testPrepareProjectionShouldThrownAnException()
    {
        // Arrange
        $connection = m::mock(Connection::class);
        $mapper = new DataMapper($connection);
        $data = ['valid' => true, 'invalid-key' => 'invalid-value'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid projection: 'invalid-key' => 'invalid-value'");

        // Act
        $this->callProtected($mapper, 'prepareProjection', [$data]);
    }

    protected function getEventService()
    {
        if (!($this->eventService ?? false)) {
            $this->eventService = m::mock(EventTriggerService::class);
            Container::instance(EventTriggerService::class, $this->eventService);
        }

        return $this->eventService;
    }

    protected function expectEventToBeFired($event, $entity, bool $halt, $return = true)
    {
        $event = 'mongolid.'.$event.': '.get_class($entity);

        $this->getEventService()->shouldReceive('fire')
            ->with($event, $entity, $halt)
            ->atLeast()
            ->once()
            ->andReturn($return);
    }

    protected function expectEventNotToBeFired($event, $entity)
    {
        $event = 'mongolid.'.$event.': '.get_class($entity);

        $this->getEventService()->shouldReceive('fire')
            ->with($event, $entity, m::any())
            ->never();
    }

    public function eventsToBailOperations()
    {
        return [
            'Saving event' => [
                'operation' => 'save',
                'dbOperation' => 'replaceOne',
                'eventName' => 'saving',
            ],
            // ------------------------
            'Inserting event' => [
                'operation' => 'insert',
                'dbOperation' => 'insertOne',
                'eventName' => 'inserting',
            ],
            // ------------------------
            'Updating event' => [
                'operation' => 'update',
                'dbOperation' => 'updateOne',
                'eventName' => 'updating',
            ],
            // ------------------------
            'Deleting event' => [
                'operation' => 'delete',
                'dbOperation' => 'deleteOne',
                'eventName' => 'deleting',
            ],
        ];
    }

    public function queryValueScenarios()
    {
        return [
            'An array' => [
                'value' => ['age' => ['$gt' => 25]],
                'expectation' => ['age' => ['$gt' => 25]],
            ],
            // ------------------------
            'An ObjectId string' => [
                'value' => '507f1f77bcf86cd799439011',
                'expectation' => ['_id' => new ObjectID('507f1f77bcf86cd799439011')],
            ],
            // ------------------------
            'An ObjectId string within a query' => [
                'value' => ['_id' => '507f1f77bcf86cd799439011'],
                'expectation' => ['_id' => new ObjectID('507f1f77bcf86cd799439011')],
            ],
            // ------------------------
            'Other type of _id, sequence for example' => [
                'value' => 7,
                'expectation' => ['_id' => 7],
            ],
            // ------------------------
            'Series of string _ids as the $in parameter' => [
                'value' => ['_id' => ['$in' => ['507f1f77bcf86cd799439011', '507f1f77bcf86cd799439012']]],
                'expectation' => [
                    '_id' => [
                        '$in' => [
                            new ObjectID('507f1f77bcf86cd799439011'),
                            new ObjectID('507f1f77bcf86cd799439012'),
                        ],
                    ],
                ],
            ],
            // ------------------------
            'Series of string _ids as the $in parameter' => [
                'value' => ['_id' => ['$nin' => ['507f1f77bcf86cd799439011']]],
                'expectation' => ['_id' => ['$nin' => [new ObjectID('507f1f77bcf86cd799439011')]]],
            ],
        ];
    }

    public function getWriteConcernVariations()
    {
        return [
            'acknowledged write concern with plain object' => [
                'object' => m::mock(),
                'writeConcern' => 1,
                'shouldFireEventAfter' => true,
                'expected' => true,
            ],
            'acknowledged write concern with attributesAccessIntesarface' => [
                'object' => m::mock(ModelInterface::class),
                'writeConcern' => 1,
                'shouldFireEventAfter' => true,
                'expected' => true,
            ],
            'unacknowledged write concern with plain object' => [
                'object' => m::mock(),
                'writeConcern' => 0,
                'shouldFireEventAfter' => false,
                'expected' => false,
            ],
            'unacknowledged write concern with attributesAccessInterface' => [
                'object' => m::mock(ModelInterface::class),
                'writeConcern' => 0,
                'shouldFireEventAfter' => false,
                'expected' => false,
            ],
        ];
    }

    /**
     * Retrieves projections that should be replaced by mapper.
     */
    public function getProjections()
    {
        return [
            'Should return self array' => [
                'projection' => ['some' => true, 'fields' => false],
                'expected' => ['some' => true, 'fields' => false],
            ],
            'Should convert number' => [
                'projection' => ['some' => 1, 'fields' => -1],
                'expected' => ['some' => true, 'fields' => false],
            ],
            'Should add true in fields' => [
                'projection' => ['some', 'fields'],
                'expected' => ['some' => true, 'fields' => true],
            ],
            'Should add boolean values according to key value' => [
                'projection' => ['-some', 'fields'],
                'expected' => ['some' => false, 'fields' => true],
            ],
        ];
    }
}
