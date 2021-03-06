<?php

/**
 * Test: Kdyby\Redis\RedisJournal.
 *
 * @testCase Kdyby\Redis\RedisJournalTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\RedisJournal;
use Nette;
use Nette\Caching\Cache;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisJournalTest extends AbstractRedisTestCase
{


	/**
	 * @var RedisJournal
	 */
	private $journal;



	protected function setUp()
	{
		parent::setUp();
		$this->journal = new RedisJournal($this->client);
	}



	public function testRemoveByTag()
	{
		$this->journal->write('ok_test1', array(
			Cache::TAGS => array('test:homepage'),
		));

		$result = $this->journal->clean(array(Cache::TAGS => array('test:homepage')));
		Assert::same(1, count($result));
		Assert::same('ok_test1', $result[0]);
	}



	public function testRemovingByMultipleTags_OneIsNotDefined()
	{
		$this->journal->write('ok_test2', array(
			Cache::TAGS => array('test:homepage', 'test:homepage2'),
		));

		$result = $this->journal->clean(array(Cache::TAGS => array('test:homepage2')));
		Assert::same(1, count($result));
		Assert::same('ok_test2', $result[0]);
	}



	public function testRemovingByMultipleTags_BothAreOnOneEntry()
	{
		$this->journal->write('ok_test2b', array(
			Cache::TAGS => array('test:homepage', 'test:homepage2'),
		));

		$result = $this->journal->clean(array(Cache::TAGS => array('test:homepage', 'test:homepage2')));
		Assert::same(1, count($result));
		Assert::same('ok_test2b', $result[0]);
	}



	public function testRemoveByMultipleTags_TwoSameTags()
	{
		$this->journal->write('ok_test2c', array(
			Cache::TAGS => array('test:homepage', 'test:homepage'),
		));

		$result = $this->journal->clean(array(Cache::TAGS => array('test:homepage', 'test:homepage')));
		Assert::same(1, count($result));
		Assert::same('ok_test2c', $result[0]);
	}



	public function testRemoveByTagAndPriority()
	{
		$this->journal->write('ok_test2d', array(
			Cache::TAGS => array('test:homepage'),
			Cache::PRIORITY => 15,
		));

		$result = $this->journal->clean(array(Cache::TAGS => array('test:homepage'), Cache::PRIORITY => 20));
		Assert::same(1, count($result));
		Assert::same('ok_test2d', $result[0]);
	}



	public function testRemoveByPriority()
	{
		$this->journal->write('ok_test3', array(
			Cache::PRIORITY => 10,
		));

		$result = $this->journal->clean(array(Cache::PRIORITY => 10));
		Assert::same(1, count($result));
		Assert::same('ok_test3', $result[0]);
	}



	public function testPriorityAndTag_CleanByTag()
	{
		$this->journal->write('ok_test4', array(
			Cache::TAGS => array('test:homepage'),
			Cache::PRIORITY => 10,
		));

		$result = $this->journal->clean(array(Cache::TAGS => array('test:homepage')));
		Assert::same(1, count($result));
		Assert::same('ok_test4', $result[0]);
	}



	public function testPriorityAndTag_CleanByPriority()
	{
		$this->journal->write('ok_test5', array(
			Cache::TAGS => array('test:homepage'),
			Cache::PRIORITY => 10,
		));

		$result = $this->journal->clean(array(Cache::PRIORITY => 10));
		Assert::same(1, count($result));
		Assert::same('ok_test5', $result[0]);
	}



	public function testMultipleWritesAndMultipleClean()
	{
		for ($i = 1; $i <= 10; $i++) {
			$this->journal->write('ok_test6_' . $i, array(
				Cache::TAGS => array('test:homepage', 'test:homepage/' . $i),
				Cache::PRIORITY => $i,
			));
		}

		$result = $this->journal->clean(array(Cache::PRIORITY => 5));
		Assert::same(5, count($result), "clean priority lower then 5");
		Assert::same('ok_test6_1', $result[0], "clean priority lower then 5");

		$result = $this->journal->clean(array(Cache::TAGS => array('test:homepage/7')));
		Assert::same(1, count($result), "clean tag homepage/7");
		Assert::same('ok_test6_7', $result[0], "clean tag homepage/7");

		$result = $this->journal->clean(array(Cache::TAGS => array('test:homepage/4')));
		Assert::same(0, count($result), "clean non exists tag");

		$result = $this->journal->clean(array(Cache::PRIORITY => 4));
		Assert::same(0, count($result), "clean non exists priority");

		$result = $this->journal->clean(array(Cache::TAGS => array('test:homepage')));
		Assert::same(4, count($result), "clean other");
		Assert::same('ok_test6_6', $result[0], "clean other");
	}



	public function testSpecialChars()
	{
		$this->journal->write('ok_test7ščřžýáíé', array(
			Cache::TAGS => array('čšřýýá', 'ýřžčýž/10')
		));

		$result = $this->journal->clean(array(Cache::TAGS => array('čšřýýá')));
		Assert::same(1, count($result));
		Assert::same('ok_test7ščřžýáíé', $result[0]);
	}



	public function testDuplicates_SameTag()
	{
		$this->journal->write('ok_test_a', array(
			Cache::TAGS => array('homepage')
		));

		$this->journal->write('ok_test_a', array(
			Cache::TAGS => array('homepage')
		));

		$result = $this->journal->clean(array(Cache::TAGS => array('homepage')));
		Assert::same(1, count($result));
		Assert::same('ok_test_a', $result[0]);
	}



	public function testDuplicates_SamePriority()
	{
		$this->journal->write('ok_test_b', array(
			Cache::PRIORITY => 12
		));

		$this->journal->write('ok_test_b', array(
			Cache::PRIORITY => 12
		));

		$result = $this->journal->clean(array(Cache::PRIORITY => 12));
		Assert::same(1, count($result));
		Assert::same('ok_test_b', $result[0]);
	}



	public function testDuplicates_DifferentTags()
	{
		$this->journal->write('ok_test_ba', array(
			Cache::TAGS => array('homepage')
		));

		$this->journal->write('ok_test_ba', array(
			Cache::TAGS => array('homepage2')
		));

		$result = $this->journal->clean(array(Cache::TAGS => array('homepage')));
		Assert::same(0, count($result));

		$result2 = $this->journal->clean(array(Cache::TAGS => array('homepage2')));
		Assert::same(1, count($result2));
		Assert::same('ok_test_ba', $result2[0]);
	}



	public function testDuplicates_DifferentPriorities()
	{
		$this->journal->write('ok_test_bb', array(
			Cache::PRIORITY => 15
		));

		$this->journal->write('ok_test_bb', array(
			Cache::PRIORITY => 20
		));

		$result = $this->journal->clean(array(Cache::PRIORITY => 30));
		Assert::same(1, count($result));
		Assert::same('ok_test_bb', $result[0]);
	}



	public function testCleanAll()
	{
		$this->journal->write('ok_test_all_tags', array(
			Cache::TAGS => array('test:all', 'test:all')
		));

		$this->journal->write('ok_test_all_priority', array(
			Cache::PRIORITY => 5,
		));

		$result = $this->journal->clean(array(Cache::ALL => TRUE));
		Assert::null($result);

		$result2 = $this->journal->clean(array(Cache::TAGS => 'test:all'));
		Assert::true(empty($result2));

	}

}

\run(new RedisJournalTest());
