.PHONY: test-unit test-integration

test-unit:
	docker compose exec shopware vendor/bin/phpunit --configuration custom/plugins/nlxNeosContent/phpunit.xml.dist

test-integration:
	docker compose exec shopware vendor/bin/phpunit --configuration custom/plugins/nlxNeosContent/phpunit.integration.xml.dist
