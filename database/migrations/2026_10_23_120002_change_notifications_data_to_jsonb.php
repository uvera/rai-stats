<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Databases that already ran the notifications migration stored `data`
     * as text. Filament's unread badge queries `data->>'format'`, which
     * Postgres only allows on json/jsonb.
     */
    public function up(): void
    {
        if ($this->dataType() === 'jsonb') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
    }

    public function down(): void
    {
        if ($this->dataType() === 'text') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
    }

    private function dataType(): ?string
    {
        $column = DB::selectOne("
            select data_type
            from information_schema.columns
            where table_schema = current_schema()
              and table_name = 'notifications'
              and column_name = 'data'
        ");

        return $column->data_type ?? null;
    }
};
