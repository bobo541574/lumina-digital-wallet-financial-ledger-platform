/**
 * Compile-time conformance between the hand-written API types in `api.ts` and the
 * generated OpenAPI schema in `types/generated/api.ts`.
 *
 * These assertions fail `npm run typecheck` when a hand-written type carries a
 * field the contract no longer defines, or a field whose type the contract
 * changed. That turns spec drift into a build error instead of a runtime bug.
 *
 * `openapi-typescript` marks every property optional (the spec omits `required`),
 * so the check runs one way: each app type must be assignable to its schema —
 * the app may narrow optionality but not invent or retype fields.
 *
 * Regenerate with `npm run types:api`; CI verifies the generated file is current.
 */

import type { components } from '@/types/generated/api';
import type {
    ApiErrorEnvelope,
    AuditEvent,
    AuthUser,
    Permission,
    Role,
    Transfer,
    UserNotification,
    Wallet,
    WalletBalance,
    Withdrawal,
} from '@/types/api';

type Schemas = components['schemas'];

/** Fails to compile unless `App` is assignable to `Schema`. */
type Conforms<Schema, App extends Schema> = App;

export type ApiErrorEnvelopeConforms = Conforms<Schemas['ErrorEnvelope'], ApiErrorEnvelope>;

export type WalletConforms = Conforms<Schemas['Wallet'], Wallet>;
export type WalletBalanceConforms = Conforms<Schemas['WalletBalance'], WalletBalance>;
export type TransferConforms = Conforms<Schemas['Transfer'], Transfer>;
export type WithdrawalConforms = Conforms<Schemas['Withdrawal'], Withdrawal>;
export type AuditEventConforms = Conforms<Schemas['AuditEvent'], AuditEvent>;
export type RoleConforms = Conforms<Schemas['Role'], Role>;
export type PermissionConforms = Conforms<Schemas['Permission'], Permission>;
export type UserNotificationConforms = Conforms<Schemas['UserNotification'], UserNotification>;
export type AuthUserConforms = Conforms<Schemas['User'], AuthUser>;
