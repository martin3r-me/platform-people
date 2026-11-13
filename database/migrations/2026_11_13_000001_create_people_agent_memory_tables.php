<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Das Gedächtnis eines Agenten gehört zu seiner Entwicklung — also zu people:
 *  - agent_memory_docs: Spiegel der steuernden Vault-Dateien (Arbeitsweise, Index, offene Fragen, Notizen)
 *  - agent_rules:       Spiegel der Regeln, aus denen die Arbeitsweise besteht
 *  - agent_curations:   Kuratier-Aufträge und -Entwürfe an das Engram
 * Quelle bleibt das Engram; es meldet über die Agent-Schnittstelle der Organisation, die hierher weiterreicht.
 *
 * Übernimmt den Bestand aus den bisherigen organization_agent_*-Tabellen mit denselben IDs — der Daemon
 * quittiert offene Aufträge per ID. Die alten Tabellen bleiben stehen, bis organization sie selbst abräumt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('people_agent_memory_docs')) {
            Schema::create('people_agent_memory_docs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('org_entity_id')->index();
                $t->string('path', 255);
                $t->string('kind', 24);
                $t->string('title')->nullable();
                $t->char('sha', 64);
                $t->longText('content');
                $t->timestamp('file_modified_at')->nullable();
                $t->timestamps();
                $t->unique(['org_entity_id', 'path'], 'people_agent_memory_docs_entity_path');
            });
            $this->copy('organization_agent_memory_docs', 'people_agent_memory_docs',
                ['id', 'path', 'kind', 'title', 'sha', 'content', 'file_modified_at', 'created_at', 'updated_at']);
        }

        if (! Schema::hasTable('people_agent_rules')) {
            Schema::create('people_agent_rules', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('org_entity_id')->index();
                $t->unsignedBigInteger('rule_id');
                $t->integer('ord')->default(0);
                $t->string('kind', 16);
                $t->string('trigger', 500)->default('');
                $t->text('text');
                $t->text('why')->nullable();
                $t->string('source', 16)->default('learned');
                $t->string('status', 16)->index();
                $t->unsignedBigInteger('supersedes')->default(0);
                $t->unsignedInteger('confirmed')->default(0);
                $t->unsignedInteger('refuted')->default(0);
                $t->char('sha', 64);
                $t->string('rule_created_at', 40)->nullable();
                $t->string('rule_updated_at', 40)->nullable();
                $t->string('last_used_at', 40)->nullable();
                $t->timestamps();
                $t->unique(['org_entity_id', 'rule_id'], 'people_agent_rules_entity_rule');
            });
            $this->copy('organization_agent_rules', 'people_agent_rules',
                ['id', 'rule_id', 'ord', 'kind', 'trigger', 'text', 'why', 'source', 'status', 'supersedes', 'confirmed',
                 'refuted', 'sha', 'rule_created_at', 'rule_updated_at', 'last_used_at', 'created_at', 'updated_at']);
        }

        if (! Schema::hasTable('people_agent_curations')) {
            Schema::create('people_agent_curations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('org_entity_id')->index();
                $t->string('path', 255);
                $t->string('op', 16);
                $t->char('base_sha', 64);
                $t->longText('content')->nullable();
                $t->text('note')->nullable();
                $t->string('status', 16)->default('pending')->index();
                $t->text('result')->nullable();
                $t->unsignedBigInteger('created_by_user_id')->nullable();
                $t->timestamp('applied_at')->nullable();
                $t->timestamps();
            });
            $this->copy('organization_agent_curations', 'people_agent_curations',
                ['id', 'path', 'op', 'base_sha', 'content', 'note', 'status', 'result', 'created_by_user_id', 'applied_at', 'created_at', 'updated_at']);
        }
    }

    /** Bestand 1:1 übernehmen (gleiche IDs); organization_entity_id heißt hier org_entity_id. */
    private function copy(string $from, string $to, array $columns): void
    {
        if (! Schema::hasTable($from)) {
            return;
        }
        $grammar = DB::getQueryGrammar();
        $cols = implode(', ', array_map(fn ($c) => $grammar->wrap($c), $columns));
        DB::statement("INSERT INTO {$grammar->wrapTable($to)} (org_entity_id, {$cols}) SELECT organization_entity_id, {$cols} FROM {$grammar->wrapTable($from)}");
    }

    public function down(): void
    {
        Schema::dropIfExists('people_agent_curations');
        Schema::dropIfExists('people_agent_rules');
        Schema::dropIfExists('people_agent_memory_docs');
    }
};
