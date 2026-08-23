<?php

namespace App\Enums;

/**
 * What a notification is about.
 *
 * The prototype's kinds were loose strings with kind-specific extras hung off
 * the record. Here the kind is an enum and the extras are a polymorphic
 * subject, so "everything about this booking" is a query.
 *
 * Each kind names the preference that governs it. Preferences are honoured:
 * the prototype saved them, read them back on the settings screen, and never
 * consulted them when creating a notification, which made the whole screen
 * decorative.
 */
enum NotificationKind: string
{
    case Booking = 'booking';
    case Bump = 'bump';
    case OfferUp = 'offer_up';
    case Billing = 'billing';
    case Request = 'request';
    case Governance = 'governance';
    case Message = 'message';
    case UnitReport = 'unit_report';
    case Questionnaire = 'questionnaire';
    case Event = 'event';
    case Ride = 'ride';
    case System = 'system';
    case Recycled = 'recycled';
    case Suspended = 'suspended';

    /**
     * The preference key that switches this kind off.
     *
     * Several kinds share one preference deliberately — a member who mutes
     * bookings means bumps and offer-ups too.
     */
    public function preference(): string
    {
        return match ($this) {
            self::Booking, self::Bump, self::OfferUp => 'bookings',
            self::Billing => 'billing',
            self::Request => 'requests',
            self::Governance => 'governance',
            self::Message => 'messages',
            self::UnitReport, self::Questionnaire => 'reports',
            self::Event, self::Ride => 'social',
            self::System, self::Recycled, self::Suspended => 'account',
        };
    }

    /**
     * Kinds a member may not switch off.
     *
     * Being recycled, suspended, or told something about your money is not
     * an interest — it is something you have to know. A preference screen
     * that lets you mute it is a trap.
     */
    public function isMandatory(): bool
    {
        return in_array($this, [
            self::Billing,
            self::System,
            self::Recycled,
            self::Suspended,
        ], true);
    }

    /**
     * Every preference key, for building the settings screen.
     *
     * @return array<int, string>
     */
    public static function preferenceKeys(): array
    {
        return array_values(array_unique(array_map(
            fn (self $kind) => $kind->preference(),
            array_filter(self::cases(), fn (self $kind) => ! $kind->isMandatory()),
        )));
    }
}
