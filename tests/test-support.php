<?php
use PHPUnit\Framework\TestCase;

class SupportSchemaTest extends TestCase {
    public function test_support_schema_exists_in_database_tools() {
        if (! class_exists('Maljani_Database_Tools')) {
            $this->markTestSkipped('Maljani_Database_Tools not available in this test environment');
            return;
        }

        $schemas = Maljani_Database_Tools::get_table_schemas();
        $this->assertIsArray($schemas);
        $this->assertArrayHasKey('support_chat', $schemas, 'support_chat schema should be registered');
    }
}
