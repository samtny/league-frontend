<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CleanOrphanedForeignKeyReferences extends Migration
{
    /**
     * Run the migrations.
     *
     * Removes rows (or nulls references) that would violate the foreign key
     * constraints added in the next migration. Ordered parent-first, so that
     * a row deleted for one violation is also accounted for by the cleanup
     * of anything that referenced it.
     *
     * MySQL does not support transactional DDL, so Laravel does not
     * automatically wrap migrations in a transaction on that connection
     * (only Postgres gets that). Since this migration is pure DML, we wrap
     * it ourselves so a failure partway through leaves nothing applied.
     *
     * @return void
     */
    public function up()
    {
        DB::transaction(function () {
            $this->cleanAll();
        });
    }

    private function cleanAll(): void
    {
        // associations.user_id -> users.id (set null)
        $this->setNullIfMissing('associations', 'user_id', 'users');

        // association_users.{user_id,association_id} (cascade)
        $this->deleteIfMissing('association_users', 'user_id', 'users');
        $this->deleteIfMissing('association_users', 'association_id', 'associations');

        // divisions.association_id -> associations.id (cascade)
        $this->deleteIfMissing('divisions', 'association_id', 'associations');

        // teams.association_id -> associations.id (cascade)
        $this->deleteIfMissing('teams', 'association_id', 'associations');

        // venues.association_id -> associations.id (cascade)
        $this->deleteIfMissing('venues', 'association_id', 'associations');

        // teams.venue_id -> venues.id (set null) - after venues cleanup above
        $this->setNullIfMissing('teams', 'venue_id', 'venues');

        // members.team_id -> teams.id (set null) - after teams cleanup above
        $this->setNullIfMissing('members', 'team_id', 'teams');

        // members.association_id -> associations.id (cascade)
        $this->deleteIfMissing('members', 'association_id', 'associations');

        // series.association_id -> associations.id (cascade)
        $this->deleteIfMissing('series', 'association_id', 'associations');

        // schedules.association_id -> associations.id (cascade)
        $this->deleteIfMissing('schedules', 'association_id', 'associations');

        // schedules.series_id -> series.id (cascade) - after series cleanup above
        $this->deleteIfMissing('schedules', 'series_id', 'series');

        // schedules.division_id -> divisions.id (set null) - after divisions cleanup above
        $this->setNullIfMissing('schedules', 'division_id', 'divisions');

        // rounds.schedule_id -> schedules.id (cascade) - after schedules cleanup above
        $this->deleteIfMissing('rounds', 'schedule_id', 'schedules');

        // rounds.series_id -> series.id (cascade)
        $this->deleteIfMissing('rounds', 'series_id', 'series');

        // rounds.division_id -> divisions.id (set null)
        $this->setNullIfMissing('rounds', 'division_id', 'divisions');

        // matches.schedule_id -> schedules.id (cascade) - after rounds cleanup above
        $this->deleteIfMissing('matches', 'schedule_id', 'schedules');

        // matches.round_id -> rounds.id (cascade)
        $this->deleteIfMissing('matches', 'round_id', 'rounds');

        // matches.series_id -> series.id (cascade)
        $this->deleteIfMissing('matches', 'series_id', 'series');

        // matches.association_id -> associations.id (cascade)
        $this->deleteIfMissing('matches', 'association_id', 'associations');

        // matches.division_id -> divisions.id (set null)
        $this->setNullIfMissing('matches', 'division_id', 'divisions');

        // matches.venue_id -> venues.id (set null)
        $this->setNullIfMissing('matches', 'venue_id', 'venues');

        // matches.home_team_id / away_team_id -> teams.id (set null)
        $this->setNullIfMissing('matches', 'home_team_id', 'teams');
        $this->setNullIfMissing('matches', 'away_team_id', 'teams');

        // result_submissions.association_id -> associations.id (cascade)
        $this->deleteIfMissing('result_submissions', 'association_id', 'associations');

        // result_submissions.schedule_id -> schedules.id (cascade)
        $this->deleteIfMissing('result_submissions', 'schedule_id', 'schedules');

        // result_submissions.match_id -> matches.id (cascade) - after matches cleanup above
        $this->deleteIfMissing('result_submissions', 'match_id', 'matches');

        // result_submissions.win_team_id -> teams.id (set null)
        $this->setNullIfMissing('result_submissions', 'win_team_id', 'teams');

        // results.match_id -> matches.id (cascade)
        $this->deleteIfMissing('results', 'match_id', 'matches');

        // results.home_team_id / away_team_id -> teams.id (set null)
        $this->setNullIfMissing('results', 'home_team_id', 'teams');
        $this->setNullIfMissing('results', 'away_team_id', 'teams');

        // team_results.schedule_id -> schedules.id (cascade)
        $this->deleteIfMissing('team_results', 'schedule_id', 'schedules');

        // team_results.match_id -> matches.id (cascade)
        $this->deleteIfMissing('team_results', 'match_id', 'matches');

        // team_results.team_id -> teams.id (cascade)
        $this->deleteIfMissing('team_results', 'team_id', 'teams');

        // contact_submissions.association_id -> associations.id (cascade)
        $this->deleteIfMissing('contact_submissions', 'association_id', 'associations');
    }

    /**
     * Reverse the migrations.
     *
     * Data cleanup cannot be meaningfully reversed.
     *
     * @return void
     */
    public function down()
    {
        //
    }

    private function deleteIfMissing(string $table, string $column, string $referencedTable): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->whereNotIn($column, DB::table($referencedTable)->select('id'))
            ->delete();
    }

    private function setNullIfMissing(string $table, string $column, string $referencedTable): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->whereNotIn($column, DB::table($referencedTable)->select('id'))
            ->update([$column => null]);
    }
}
