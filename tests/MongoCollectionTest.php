<?php
require_once 'PHPUnit/Framework.php';

/**
 * Test class for MongoCollection.
 * Generated by PHPUnit on 2009-04-10 at 13:30:28.
 */
class MongoCollectionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var    MongoCollection
     * @access protected
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        $db = new MongoDB($this->sharedFixture, "phpunit");
        $this->object = $db->selectCollection('c');
        $this->object->drop();
    }
    
    public function test__toString() {
        $this->assertEquals((string)$this->object, 'phpunit.c');
    }

    public function testGetName() {
        $this->assertEquals($this->object->getName(), 'c');
    }

    public function testDrop() {
        $ns = $this->object->db->selectCollection('system.namespaces');

        $this->object->insert(array('x' => 1));
        $this->object->ensureIndex('x');

        $c = $ns->findOne(array('name' => 'phpunit.c'));
        $this->assertNotNull($c);

        $this->object->drop();

        $c = $ns->findOne(array('name' => 'phpunit.c'));
        $this->assertEquals(null, $c);
    }

    public function testValidate() {
      $v = $this->object->validate();
      $this->assertEquals($v['ok'], 0);
      $this->assertEquals($v['errmsg'], 'ns not found');

      $this->object->insert(array('a' => 'foo'));
      $v = $this->object->validate();
      $this->assertEquals($v['ok'], 1);
      $this->assertEquals($v['ns'], 'phpunit.c');
      $this->assertNotNull($v['result']);
    }

    public function testInsert() {
      $a = array("n" => NULL,
                 "l" => 234234124,
                 "d" => 23.23451452,
                 "b" => true,
                 "a" => array("foo"=>"bar",
                              "n" => NULL,
                              "x" => new MongoId("49b6d9fb17330414a0c63102")),
                 "d2" => new MongoDate(1271079861),
                 "regex" => new MongoRegex("/xtz/g"),
                 "_id" => new MongoId("49b6d9fb17330414a0c63101"),
                 "string" => "string");
      
      $this->assertTrue($this->object->insert($a));
      $obj = $this->object->findOne();

      $this->assertEquals($obj['n'], null);
      $this->assertEquals($obj['l'], 234234124);
      $this->assertEquals($obj['d'], 23.23451452);
      $this->assertEquals($obj['b'], true);
      $this->assertEquals($obj['a']['foo'], 'bar');
      $this->assertEquals($obj['a']['n'], null);
      $this->assertNotNull($obj['a']['x']);
      $this->assertEquals($obj['d2']->sec, 1271079861);
      $this->assertEquals($obj['d2']->usec, 0);
      $this->assertEquals($obj['regex']->regex, 'xtz');
      $this->assertEquals($obj['regex']->flags, 'g');
      $this->assertNotNull($obj['_id']);
      $this->assertEquals($obj['string'], 'string');

      $this->assertFalse($this->object->insert(array()));
      $this->assertTrue($this->object->insert(array(1,2,3,4,5)));
    }

    public function testInsert2() {
      $this->assertTrue($this->object->insert(array(NULL)));
      $this->assertTrue($this->object->insert(array(NULL=>"1")));
      
      $this->assertEquals($this->object->count(), 2);
      $cursor = $this->object->find();

      $x = $cursor->getNext();
      $this->assertTrue(array_key_exists('0', $x), json_encode($x));
      $this->assertEquals($x['0'], null);

      $x = $cursor->getNext();
      $this->assertTrue(array_key_exists('', $x));
      $this->assertEquals($x[''], '1');
    }

    public function testInsertNonAssoc() {
        $nonassoc = array("x", "y", "z");
        $this->object->insert($nonassoc);
        $x = $this->object->findOne();

        $this->assertEquals("x", $x[0]);
        $this->assertEquals("y", $x[1]);
        $this->assertEquals("z", $x[2]);
        $this->assertEquals((string)$nonassoc['_id'], (string)$x['_id']);
    }
    
    public function testBatchInsert() {
      $this->assertFalse($this->object->batchInsert(array()));
      $this->assertFalse($this->object->batchInsert(array(1,2,3)));
      $this->assertTrue($this->object->batchInsert(array('z'=>array('foo'=>'bar'))));

      $a = array( array( "x" => "y"), array( "x"=> "z"), array("x"=>"foo"));
      $this->object->batchInsert($a);
      $this->assertEquals(4, $this->object->count());

      $cursor = $this->object->find()->sort(array("x" => -1));
      $x = $cursor->getNext();
      $this->assertEquals('bar', $x['foo']);
      $x = $cursor->getNext();
      $this->assertEquals('z', $x['x']);
      $x = $cursor->getNext();
      $this->assertEquals('y', $x['x']);
      $x = $cursor->getNext();
      $this->assertEquals('foo', $x['x']);
    }

    public function testFind() {
      for ($i=0;$i<50;$i++) {
        $this->object->insert(array('x' => $i));
      }

      $c = $this->object->find();
      $this->assertEquals(iterator_count($c), 50);
      $c = $this->object->find(array());
      $this->assertEquals(iterator_count($c), 50);

      $this->object->insert(array("foo" => "bar",
                                  "a" => "b",
                                  "b" => "c"));

      $c = $this->object->find(array('foo' => 'bar'), array('a'=>1, 'b'=>1));

      $this->assertTrue($c instanceof MongoCursor);
      $obj = $c->getNext();
      $this->assertEquals('b', $obj['a']);
      $this->assertEquals('c', $obj['b']);
      $this->assertEquals(false, array_key_exists('foo', $obj));
    }

    
    public function testFindWhere() {
        for($i=0;$i<50; $i++) {
            $this->object->insert(array( "foo$i" => pow(2, $i)));
        }

        $x = $this->object->findOne(array('$where' => new MongoCode('function() { return this.foo23 != null; }')));
        $this->assertArrayHasKey('foo23', $x, json_encode($x));
        $this->assertEquals(8388608, $x['foo23'], json_encode($x));
    }
    

    public function testFindOne() {
      $this->assertEquals(null, $this->object->findOne());
      $this->assertEquals(null, $this->object->findOne(array()));

      for ($i=0;$i<3;$i++) {
        $this->object->insert(array('x' => $i));
      }

      $obj = $this->object->findOne();
      $this->assertNotNull($obj);
      $this->assertEquals($obj['x'], 0);

      $obj = $this->object->findOne(array('x' => 1));
      $this->assertNotNull($obj);
      $this->assertEquals(1, $obj['x']);
    }

    public function testFindOneFields() {
      for ($i=0;$i<3;$i++) {
        $this->object->insert(array('x' => $i, 'y' => 4, 'z' => 6));
      }

      $obj = $this->object->findOne(array(), array('y'));
      $this->assertArrayHasKey('y', $obj, json_encode($obj));
      $this->assertArrayHasKey('_id', $obj, json_encode($obj));
      $this->assertArrayNotHasKey('x', $obj, json_encode($obj));
      $this->assertArrayNotHasKey('z', $obj, json_encode($obj));

      $obj = $this->object->findOne(array(), array('y'=>1, 'z'=>1));
      $this->assertArrayHasKey('y', $obj, json_encode($obj));
      $this->assertArrayHasKey('_id', $obj, json_encode($obj));
      $this->assertArrayNotHasKey('x', $obj, json_encode($obj));
      $this->assertArrayHasKey('z', $obj, json_encode($obj));
    }

    public function testUpdate() {
      $old = array("foo"=>"bar", "x"=>"y");
      $new = array("foo"=>"baz");
      
      $this->object->update(array("foo"=>"bar"), $old, true);
      $obj = $this->object->findOne();
      $this->assertEquals($obj['foo'], 'bar');      
      $this->assertEquals($obj['x'], 'y');      

      $this->object->update($old, $new);
      $obj = $this->object->findOne();
      $this->assertEquals($obj['foo'], 'baz');      
    }

    public function testRemove() {
      for($i=0;$i<15;$i++) {
        $this->object->insert(array("i"=>$i));
      }
      
      $this->assertEquals($this->object->count(), 15);
      $this->object->remove(array(), true);
      $this->assertEquals($this->object->count(), 14);

      $this->object->remove(array());
      $this->assertEquals($this->object->count(), 0);

      for($i=0;$i<15;$i++) {
        $this->object->insert(array("i"=>$i));
      }

      $this->assertEquals($this->object->count(), 15);
      $this->object->remove();      
      $this->assertEquals($this->object->count(), 0);
    }

    public function testEnsureIndex() {
      $this->object->ensureIndex('foo');

      $idx = $this->object->db->selectCollection('system.indexes');
      $index = $idx->findOne(array('name' => 'foo_1'));

      $this->assertNotNull($index);
      $this->assertEquals($index['key']['foo'], 1);
      $this->assertEquals($index['name'], 'foo_1');

      $this->object->ensureIndex("");
      $index = $idx->findOne(array('name' => '_1'));
      $this->assertEquals(null, $index);

      // get rid of indexes
      $this->object->drop();

      $this->object->ensureIndex(null);
      $index = $idx->findOne(array('name' => '_1'));
      $this->assertEquals(null, $index);

      $this->object->ensureIndex(array('bar' => -1));
      $index = $idx->findOne(array('name' => 'bar_-1'));
      $this->assertNotNull($index);
      $this->assertEquals($index['key']['bar'], -1);
      $this->assertEquals($index['ns'], 'phpunit.c');
    }

    public function testEnsureUniqueIndex() {
      $unique = true;

      $this->object->ensureIndex(array('x'=>1), !$unique);
      $this->object->insert(array('x'=>0, 'z'=>1));
      $this->object->insert(array('x'=>0, 'z'=>2));
      $this->assertEquals($this->object->count(), 2);

      $this->object->ensureIndex(array('z'=>1), $unique);
      $this->object->insert(array('z'=>0));
      $this->object->insert(array('z'=>0));
      $err = $this->sharedFixture->lastError();
      $this->assertEquals("E11000", substr($err['err'], 0, 6), json_encode($err));
    }

    public function testDeleteIndex() {
      $idx = $this->object->db->selectCollection('system.indexes');

      $this->object->ensureIndex('foo');
      $this->object->ensureIndex(array('foo' => -1));

      $cursor = $idx->find(array('ns' => 'phpunit.c'));
      $num = iterator_count($cursor);
      $this->assertEquals(3, $num);

      $this->object->deleteIndex(null);
      $num = iterator_count($idx->find(array('ns' => 'phpunit.c')));
      $this->assertEquals(3, $num);

      $this->object->deleteIndex(array('foo' => 1)); 
      $num = iterator_count($idx->find(array('ns' => 'phpunit.c')));
      $this->assertEquals(2, $num);

      $this->object->deleteIndex('foo');
      $num = iterator_count($idx->find(array('ns' => 'phpunit.c')));
      $this->assertEquals(2, $num);

      $this->object->deleteIndex(array('foo' => -1));
      $num = iterator_count($idx->find(array('ns' => 'phpunit.c')));
      $this->assertEquals(1, $num);
    }

    public function testDeleteIndexes() {
      $idx = $this->object->db->selectCollection('system.indexes');

      $this->object->ensureIndex(array('foo' => 1));
      $this->object->ensureIndex(array('foo' => -1));
      $this->object->ensureIndex(array('bar' => 1, 'baz' => -1));

      $num = iterator_count($idx->find(array('ns' => 'phpunit.c')));
      $this->assertEquals($num, 4);

      $this->object->deleteIndexes();
      $num = iterator_count($idx->find(array('ns' => 'phpunit.c')));
      $this->assertEquals($num, 1);
    }

    public function testGetIndexInfo() {
      $info = $this->object->getIndexInfo();
      $this->assertEquals(count($info), 0);

      $this->object->ensureIndex(array('foo' => 1));
      $this->object->ensureIndex(array('foo' => -1));
      $this->object->ensureIndex(array('bar' => 1, 'baz' => -1));

      $info = $this->object->getIndexInfo();
      $this->assertEquals(4, count($info), json_encode($info));
      $this->assertEquals($info[1]['key']['foo'], 1);
      $this->assertEquals($info[1]['name'], 'foo_1');
      $this->assertEquals($info[2]['key']['foo'], -1);
      $this->assertEquals($info[2]['name'], 'foo_-1');
      $this->assertEquals($info[3]['key']['bar'], 1);
      $this->assertEquals($info[3]['key']['baz'], -1);
      $this->assertEquals($info[3]['name'], 'bar_1_baz_-1');
    }
    
    public function testCount() {
      $this->assertEquals($this->object->count(), 0);

      $this->object->insert(array(6));

      $this->assertEquals($this->object->count(), 1);

      $this->assertEquals(0, $this->object->count(array('z'=>1)));
      $this->assertEquals(1, $this->object->count(array('0'=>6)));
      $this->assertEquals(1, $this->object->count(array(), array('0'=>1)));
    }
    

    public function testSave() {
      $this->object->save(array('x' => 1));

      $a = $this->object->findOne();
      $id1 = $a['_id'];

      $a['x'] = 2;
      $this->object->save($a);
      $id2 = $a['_id'];

      $this->assertEquals($id1, $id2);
      $a['y'] = 3;
      $this->object->save($a);

      $this->assertEquals($this->object->count(), 1);

      $a = $this->object->findOne();
      $this->assertEquals($a['x'], 2);


    }

    public function testGetDBRef() {
        for($i=0;$i<50;$i++) {
            $this->object->insert(array('x' => rand()));
        }
        $obj = $this->object->findOne();

        $ref = $this->object->createDBRef($obj);
        $obj2 = $this->object->getDBRef($ref);

        $this->assertNotNull($obj2);
        $this->assertEquals($obj['x'], $obj2['x']);
    }

    public function testCreateDBRef() {
        $ref = $this->object->createDBRef(array('foo' => 'bar'));
        $this->assertEquals($ref, null);

        $arr = array('_id' => new MongoId());
        $ref = $this->object->createDBRef($arr);
        $this->assertNotNull($ref);
        $this->assertTrue(is_object($ref));

        $arr = array('_id' => 1);
        $ref = $this->object->createDBRef($arr);
        $this->assertNotNull($ref);
        $this->assertTrue(is_object($ref));

        $ref = $this->object->createDBRef(new MongoId());
        $this->assertNotNull($ref);
        $this->assertTrue(is_object($ref));
    }


    public function testToIndexString() {
        $this->assertEquals(TestToIndexString::test(null), '_1');
        $this->assertEquals(TestToIndexString::test(52), '52_1');
        $this->assertEquals(TestToIndexString::test('x'), 'x_1');
        $this->assertEquals(TestToIndexString::test('x.y.z'), 'x_y_z_1');
        $this->assertEquals(TestToIndexString::test('x_y.z'), 'x_y_z_1');
        $this->assertEquals(TestToIndexString::test(array('x' => 1)), 'x_1');
        $this->assertEquals(TestToIndexString::test(array('x' => -1)), 'x_-1');
        $this->assertEquals(TestToIndexString::test(array('x' => 1, 'y' => -1)), 'x_1_y_-1');
    }

    public function testGroup() {

        $g = $this->object->group(array(), array("count"=> 0), "function (obj, prev) { prev.count++; }", array());

        $this->assertEquals(0, count($g));

        $this->object->save(array("a" => 2));
        $this->object->save(array("b" => 5));
        $this->object->save(array("a" => 1));
 
        $g = $this->object->group(array(), array("count" => 0), "function (obj, prev) { prev.count++; }", array());
        $this->assertEquals(1, count($g));
        $this->assertEquals(3, $g[0]['count']);
      
        $g = $this->object->group(array(), array("count" => 0), "function (obj, prev) { prev.count++; }", array("a" => array( '$gt' => 1)));
        $this->assertEquals(1, count($g)); 
        $this->assertEquals(1, $g[0]['count']);
    }

}

class TestToIndexString extends MongoCollection {
    public static function test($obj) {
        return parent::toIndexString($obj);
    }
}

?>
