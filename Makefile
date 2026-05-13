# Rice テスト実行 Makefile (Phase 6)
#
# Usage:
#   make test-unit          - Unit テストのみ
#   make test-feature       - Feature テストのみ
#   make test-phase6        - Phase 6 関連テスト一式 (Unit + Feature)
#   make test-gui           - Playwright GUI テスト
#   make test-integration   - @group integration の統合テスト (要 docker)
#   make test-all           - 全テスト (上記すべて順に)
#
# すべて docker compose 経由で laravel コンテナ内で実行する想定。
# ローカル PHP で実行したい場合は `cd laravel && php artisan test ...` を使ってください。

DC = docker compose
ART = $(DC) exec -T laravel php artisan

.PHONY: help test-unit test-feature test-gui test-integration test-phase6 test-all

help:
	@echo "Targets: test-unit / test-feature / test-gui / test-integration / test-phase6 / test-all"

test-unit:
	$(ART) test --testsuite=Unit

test-feature:
	$(ART) test --testsuite=Feature

test-phase6:
	$(ART) test --filter='Phase6|RagCollectionResolver|AdoptionEvaluator|WorkflowRuleMatch|UserSignature'

test-gui:
	cd tests/gui && npx playwright test

test-integration:
	$(ART) test --group=integration

test-all: test-unit test-feature test-gui test-integration
	@echo "All tests completed."
