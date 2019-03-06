<?php
	namespace DaybreakStudios\PrometheusClient\Adapter;

	use DaybreakStudios\PrometheusClient\Adapter\Filesystem\FilesystemIterator;
	use DaybreakStudios\PrometheusClient\Adapter\Filesystem\FilesystemLock;

	class FilesystemAdapter implements AdapterInterface {
		/**
		 * @var string
		 */
		protected $basePath;

		/**
		 * @var string[]
		 */
		protected $keyCache = [];

		/**
		 * FilesystemAdapter constructor.
		 *
		 * @param string $basePath
		 */
		public function __construct($basePath) {
			$this->basePath = rtrim($basePath, '\\/');

			if (!is_readable($this->basePath) || !is_writable($this->basePath))
				throw new \RuntimeException($this->basePath . ' must be readable and writeable by PHP');
		}

		/**
		 * {@inheritdoc}
		 */
		public function exists($key) {
			return file_exists($this->getPath($key));
		}

		/**
		 * {@inheritdoc}
		 */
		public function set($key, $value) {
			return file_put_contents($this->getPath($key), $this->serialize($value)) !== false;
		}

		/**
		 * {@inheritdoc}
		 */
		public function get($key, $def = null) {
			if (!$this->exists($key))
				return $def;

			return $this->unserialize(file_get_contents($this->getPath($key)));
		}

		/**
		 * {@inheritdoc}
		 */
		public function create($key, $value) {
			if ($this->exists($key))
				return false;

			$this->set($key, $value);

			return true;
		}

		/**
		 * {@inheritdoc}
		 */
		public function increment($key, $step = 1, $initialValue = 0) {
			$this->create($key, $initialValue);

			return $this->compareAndSwap(
				$key,
				function($old) use ($step) {
					return $old + $step;
				}
			);
		}

		/**
		 * {@inheritdoc}
		 */
		public function decrement($key, $step = 1, $initialValue = 0) {
			$this->create($key, $initialValue);

			return $this->compareAndSwap(
				$key,
				function($old) use ($step) {
					return $old - $step;
				}
			);
		}

		/**
		 * {@inheritdoc}
		 */
		public function delete($key) {
			if (!$this->exists($key))
				return false;

			unlink($this->getPath($key));

			return true;
		}

		/**
		 * {@inheritdoc}
		 */
		public function compareAndSwap($key, callable $mutator, $timeout = 500) {
			if (!$this->exists($key))
				return false;

			$path = $this->getPath($key);
			$lock = new FilesystemLock($path);

			if (!$lock->await($timeout))
				return false;

			$success = $this->set($key, call_user_func($mutator, $this->get($key)));

			$lock->release();

			return $success;
		}

		/**
		 * {@inheritdoc}
		 */
		public function search($prefix) {
			$prefix = $this->encodeFilename($prefix);
			$keys = [];

			foreach (scandir($this->basePath) as $item) {
				if ($item === '.' || $item === '..')
					continue;

				if (strpos($item, $prefix) === 0)
					$keys[] = $this->decodeFilename($item);
			}

			return new FilesystemIterator($this, $keys);
		}

		/**
		 * @param string $key
		 *
		 * @return string
		 */
		protected function getPath($key) {
			return $this->basePath . DIRECTORY_SEPARATOR . $this->encodeFilename($key);
		}

		/**
		 * @param string $key
		 *
		 * @return string
		 */
		protected function encodeFilename($key) {
			if (!isset($this->keyCache[$key]))
				$this->keyCache[$key] = base64_encode(strtr($key, '=+/', '-_.'));

			return $this->keyCache[$key];
		}

		/**
		 * @param string $encodedFilename
		 *
		 * @return string
		 */
		protected function decodeFilename($encodedFilename) {
			return base64_decode(strtr($encodedFilename, '-_.', '=+/'));
		}

		/**
		 * @param mixed $value
		 *
		 * @return string
		 */
		protected function serialize($value) {
			return serialize($value);
		}

		/**
		 * @param string $data
		 *
		 * @return mixed
		 */
		protected function unserialize($data) {
			return unserialize($data);
		}
	}