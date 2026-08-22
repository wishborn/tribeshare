<?php

namespace App\Enums;

/**
 * What a proposal does when it carries.
 *
 * Every one of these executes through the ordinary guarded services, so a
 * vote is subject to the same invariants as any other actor.
 */
enum ProposalType: string
{
    case ChangeFee = 'change_fee';
    case AddMember = 'add_member';
    case RemoveMember = 'remove_member';
    case GrantHat = 'grant_hat';
    case RevokeHat = 'revoke_hat';
    case ChangeAssetSetting = 'change_asset_setting';
    case ChangeGovernanceModel = 'change_governance_model';
    case ChangeDamageDeposit = 'change_damage_deposit';
    case ChangeInsurance = 'change_insurance';
    case ChangeOverhead = 'change_overhead';

    /**
     * Undo an earlier decision, and lift the locks it placed.
     *
     * Reached through governance itself — a decision is undone the same way
     * it was made. The prototype declared a standalone repeal action and
     * never implemented it, while this worked all along.
     */
    case Repeal = 'repeal';
}
