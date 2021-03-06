<?php

/**
 * Test: Kdyby\Redis\RedisClient.
 *
 * @testCase Kdyby\Redis\RedisClientTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\RedisClient;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisClientTest extends AbstractRedisTestCase
{

	/**
	 * @var string
	 */
	private $ns;



	public function setUp()
	{
		parent::setUp();
		$this->ns = Nette\Utils\Strings::random();
	}



	public function testPrimitives()
	{
		$secret = "I'm batman";
		$key = $this->ns . 'redis-test-secred';

		$this->client->set($key, $secret);
		$this->client->expire($key, 10);

		Assert::same($secret, $this->client->get($key));
	}



	public function testLargeData()
	{
		$data = str_repeat('Kdyby', 1e6);
		$this->client->set('large', $data);
		Assert::same($data, $this->client->get('large'));
	}



	public function testNullReply()
	{
		Assert::false($this->client->get('nonexistingkey'));
	}



	public function testExec()
	{
		Assert::equal(1, $this->client->sadd('test:key', 'item1'));
		Assert::equal(1, $this->client->sadd('test:key', 'item2'));

		Assert::equal('OK', $this->client->multi());
		Assert::equal('QUEUED', $this->client->sMembers('test:key'));
		Assert::equal('QUEUED', $this->client->sMembers('test:key'));

		list($first, $second) = $this->client->exec();
		sort($first);
		sort($second);
		Assert::equal(array('item1', 'item2'), $first);
		Assert::equal(array('item1', 'item2'), $second);
	}



	public function testExecWithClosure()
	{
		Assert::equal(1, $this->client->sadd('test:key', 'item1'));
		Assert::equal(1, $this->client->sadd('test:key', 'item2'));

		list($first, $second) = $this->client->multi(function (RedisClient $client) {
			$client->sMembers('test:key');
			$client->sMembers('test:key');
		});

		sort($first);
		sort($second);
		Assert::equal(array('item1', 'item2'), $first);
		Assert::equal(array('item1', 'item2'), $second);
	}



	public function testExecException()
	{
		$other = new RedisClient();
		$client = $this->client;

		Assert::exception(function () use ($other, $client) {
			$client->set('foo', 1);
			$client->watch('foo');

			$client->multi();
			$other->del('foo');
			$client->incr('foo');
			$client->exec();

		}, 'Kdyby\Redis\TransactionException');
	}

}

\run(new RedisClientTest());
