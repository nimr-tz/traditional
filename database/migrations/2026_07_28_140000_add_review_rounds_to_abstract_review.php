<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NEW_INDEX = 'abstract_review_reviewer_round_unique';

    /**
     * Numbers each pass of review so a revision becomes a *re-review* rather
     * than a fresh one.
     *
     * Previously a resubmission hard-deleted the reviewer's recommendation, and
     * their written comments cascaded away with it — so a reviewer re-opening a
     * revised abstract could not see what they had asked for, and the record of
     * why a revision was requested was lost for good.
     *
     * Now every decision carries the round it belongs to. Superseded rounds are
     * kept, which both preserves the audit trail and lets a reviewer read their
     * own earlier comments while judging the revision.
     *
     * Written defensively: MySQL commits each DDL statement immediately, so a
     * failure part-way through leaves the schema half-changed and the migration
     * unrecorded. Every step here checks before it acts.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('abstract_submissions', 'review_round')) {
            Schema::table('abstract_submissions', function (Blueprint $table) {
                $table->unsignedInteger('review_round')->default(1)->after('status');
            });
        }

        if (! Schema::hasColumn('abstract_reviewer_decisions', 'round')) {
            Schema::table('abstract_reviewer_decisions', function (Blueprint $table) {
                $table->unsignedInteger('round')->default(1)->after('reviewer_id');
            });
        }

        // One decision per reviewer *per round*. The old constraint allowed only
        // one ever, which is why superseded rows previously had to be deleted.
        //
        // The replacement is created first: the old unique index is the only one
        // covering the abstract_submission_id foreign key, and MySQL refuses to
        // drop an index a constraint still depends on. The new index leads with
        // the same column, so it takes over that duty before the drop.
        if (! $this->hasIndex(self::NEW_INDEX)) {
            Schema::table('abstract_reviewer_decisions', function (Blueprint $table) {
                $table->unique(['abstract_submission_id', 'reviewer_id', 'round'], self::NEW_INDEX);
            });
        }

        foreach ($this->staleUniqueIndexes() as $name) {
            Schema::table('abstract_reviewer_decisions', function (Blueprint $table) use ($name) {
                $table->dropUnique($name);
            });
        }
    }

    public function down(): void
    {
        if (! $this->hasIndex('abstract_review_decision_reviewer_unique')) {
            Schema::table('abstract_reviewer_decisions', function (Blueprint $table) {
                $table->unique(['abstract_submission_id', 'reviewer_id'], 'abstract_review_decision_reviewer_unique');
            });
        }

        if ($this->hasIndex(self::NEW_INDEX)) {
            Schema::table('abstract_reviewer_decisions', function (Blueprint $table) {
                $table->dropUnique(self::NEW_INDEX);
            });
        }

        Schema::table('abstract_reviewer_decisions', function (Blueprint $table) {
            $table->dropColumn('round');
        });

        Schema::table('abstract_submissions', function (Blueprint $table) {
            $table->dropColumn('review_round');
        });
    }

    /** @return list<string> Unique indexes on (abstract_submission_id, reviewer_id) without the round. */
    private function staleUniqueIndexes(): array
    {
        return array_values(array_filter(
            array_keys($this->indexes()),
            fn (string $name) => $name !== self::NEW_INDEX
                && $name !== 'PRIMARY'
                && $this->indexes()[$name]['unique']
                && $this->indexes()[$name]['columns'] === ['abstract_submission_id', 'reviewer_id'],
        ));
    }

    private function hasIndex(string $name): bool
    {
        return array_key_exists($name, $this->indexes());
    }

    /** @return array<string, array{unique: bool, columns: list<string>}> */
    private function indexes(): array
    {
        $indexes = [];

        foreach (Schema::getIndexes('abstract_reviewer_decisions') as $index) {
            $indexes[$index['name']] = [
                'unique' => (bool) $index['unique'],
                'columns' => array_values($index['columns']),
            ];
        }

        return $indexes;
    }
};
