.PHONY: unit-test integration-test


unit-test:
	./vendor/bin/phpunit --testsuite="Symfony MessengerRedis Unit Test Suite"
integration-test:
	./vendor/bin/phpunit --testsuite="Symfony MessengerRedis Integration Test Suite"