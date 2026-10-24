<?php

namespace Tests\Feature\Filament;

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionListTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_and_scopes_to_the_current_user_by_default(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();
        $account = Account::factory()->for($me)->create();

        $mine = Transaction::factory()->for($account)->for($me)->create([
            'place' => 'My Place',
            'type' => TransactionType::Pos,
        ]);
        $theirs = Transaction::factory()->for($account)->for($someoneElse)->create([
            'place' => 'Their Place',
            'type' => TransactionType::Pos,
        ]);

        $this->actingAs($me);

        Livewire::test(ListTransactions::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_the_everyone_scope_shows_all_users_transactions(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();
        $account = Account::factory()->for($me)->create();

        $mine = Transaction::factory()->for($account)->for($me)->create();
        $theirs = Transaction::factory()->for($account)->for($someoneElse)->create();

        $this->actingAs($me);

        Livewire::test(ListTransactions::class)
            ->filterTable('scope', 'all')
            ->assertCanSeeTableRecords([$mine, $theirs]);
    }

    public function test_only_admins_may_categorize_a_transaction(): void
    {
        $member = User::factory()->create();
        $memberAccount = Account::factory()->for($member)->create();
        $memberTransaction = Transaction::factory()->for($memberAccount)->for($member)->create();

        $admin = User::factory()->admin()->create();
        $adminAccount = Account::factory()->for($admin)->create();
        $transaction = Transaction::factory()->for($adminAccount)->for($admin)->create();

        $this->actingAs($member);
        Livewire::test(ListTransactions::class)
            ->assertTableActionHidden('categorize', $memberTransaction);

        $this->actingAs($admin);
        Livewire::test(ListTransactions::class)
            ->assertTableActionVisible('categorize', $transaction);
    }
}
