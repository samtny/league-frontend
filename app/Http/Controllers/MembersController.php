<?php

namespace App\Http\Controllers;

use App\Association;
use App\Member;
use App\Team;
use Bouncer;
use Illuminate\Http\Request;

class MembersController extends Controller
{

    public function roster(Association $association, Team $team) {
        return view('team.roster', [
            'association' => $association,
            'team' => $team,
        ]);
    }

    public function create(Association $association, Team $team) {
        return view('member.create', [
            'association' => $association,
            'team' => $team,
        ]);
    }

    public function store(Association $association, Team $team, Request $request) {
        if (Bouncer::can('create', Member::class)) {
            $validatedData = $request->validate([
                'name' => 'required|max:128',
                'role' => 'required|in:Player,Captain,Reserve',
            ]);

            $member = new Member;

            $member->name = $request->name;
            $member->role = $request->role;
            $member->team_id = $team->id;
            $member->association_id = $association->id;

            $member->save();

            return redirect()->route('team.roster', ['association' => $association, 'team' => $team]);
        }
        else {
            return view('denied');
        }
    }

    public function edit(Association $association, Member $member) {
        return view('member.edit', [
            'association' => $association,
            'member' => $member,
        ]);
    }

    public function update(Association $association, Member $member, Request $request) {
        if (Bouncer::can('update', Member::class)) {
            $validatedData = $request->validate([
                'name' => 'required|max:128',
                'role' => 'required|in:Player,Captain,Reserve',
            ]);

            $member->name = $request->name;
            $member->role = $request->role;

            $member->save();

            return redirect()->route('team.roster', ['association' => $association, 'team' => $member->team]);
        }
        else {
            return view('denied');
        }
    }

    public function deleteConfirm(Association $association, Member $member) {
        if (Bouncer::can('delete', Member::class)) {
            return view('member.delete', ['member' => $member]);
        }
        else {
            return view('denied');
        }
    }

    public function delete(Association $association, Member $member) {
        if (Bouncer::can('delete', Member::class)) {
            $team = $member->team;

            $member->delete();

            return redirect()->route('team.roster', ['association' => $association, 'team' => $team])->with('success', 'Member deleted successfully.');
        }
        else {
            return view('denied');
        }
    }

}
