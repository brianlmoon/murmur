# OAuth Authentication

Murmur supports OAuth authentication with Google, Facebook, and Apple. Users can sign in using their existing accounts from these providers, and can link multiple OAuth providers to a single Murmur account.

## Features

- **Server-side OAuth flow** - Secure authentication using authorization code flow
- **Auto-linking by email** - Automatically links OAuth accounts with existing Murmur accounts that share the same email
- **Multiple providers** - Users can link Google, Facebook, and Apple to one account
- **Username selection** - New OAuth users choose a username on first login
- **Per-provider toggles** - Administrators can enable/disable each provider independently
- **OAuth-only accounts** - Users can sign in exclusively with OAuth (no password required)
- **Account unlinking** - Users can disconnect OAuth providers (requires password or another OAuth provider)

## Configuration

### 1. Set up OAuth applications

You'll need to create OAuth applications with each provider you want to support:

#### Google OAuth

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Navigate to **APIs & Services** → **Credentials**
4. Click **Create Credentials** → **OAuth client ID**
5. Select **Web application** as the application type
6. Add authorized redirect URI: `https://your-domain.com/oauth/google/callback`
   - For subdirectory installations: `https://your-domain.com/murmur/oauth/google/callback`
7. Note your **Client ID** and **Client Secret**

**Required scopes:** `openid`, `email`, `profile`

#### Facebook OAuth

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create a new app or select an existing one
3. Add **Facebook Login** product to your app
4. Navigate to **Facebook Login** → **Settings**
5. Add OAuth redirect URI: `https://your-domain.com/oauth/facebook/callback`
   - For subdirectory installations: `https://your-domain.com/murmur/oauth/facebook/callback`
6. Go to **Settings** → **Basic** to find your **App ID** and **App Secret**

**Required permissions:** `public_profile`, `email`

#### Apple OAuth

1. Go to [Apple Developer](https://developer.apple.com/)
2. Navigate to **Certificates, Identifiers & Profiles**
3. Create a new **App ID** (if you don't have one)
4. Create a **Services ID** for Sign in with Apple
5. Configure the **Return URLs**: `https://your-domain.com/oauth/apple/callback`
   - For subdirectory installations: `https://your-domain.com/murmur/oauth/apple/callback`
6. Create a **Key** for Sign in with Apple
7. Note your **Services ID**, **Team ID**, **Key ID**, and download the **private key file** (.p8)

**Required scopes:** `name`, `email`

### 2. Add credentials to config.ini

Edit `etc/config.ini` and add your OAuth credentials:

```ini
# Google OAuth
oauth.google.client_id = "your-client-id.apps.googleusercontent.com"
oauth.google.client_secret = "your-client-secret"

# Facebook OAuth
oauth.facebook.client_id = "your-app-id"
oauth.facebook.client_secret = "your-app-secret"

# Apple OAuth
oauth.apple.client_id = "com.yourdomain.yourapp"
oauth.apple.team_id = "YOUR_TEAM_ID"
oauth.apple.key_id = "YOUR_KEY_ID"
oauth.apple.private_key_path = "/path/to/AuthKey_KEYID.p8"
```

**Important notes:**
- Apple's `client_id` is your **Services ID**, not your App ID
- The private key file must be readable by the web server
- Keep these credentials secure - never commit them to version control

### 3. Enable providers in admin settings

1. Log in to your Murmur instance as an administrator
2. Navigate to **Admin** → **Settings**
3. Scroll to the **OAuth Providers** section
4. Check the box for each provider you want to enable
5. Click **Save Changes**

The admin interface will show whether each provider is properly configured. If credentials are missing or invalid, you'll see a "Not configured" warning.

## Usage

### For Users

#### Signing in with OAuth

1. Go to the login page
2. Click **Sign in with [Provider]**
3. Authorize the application on the provider's website
4. If it's your first time: choose a username to complete registration
5. You're logged in!

#### Linking additional OAuth providers

1. Log in to your Murmur account
2. Go to **Settings** → **Connected Accounts**
3. Click **Link** next to the provider you want to add
4. Authorize on the provider's website
5. The provider is now linked to your account

#### Unlinking OAuth providers

1. Go to **Settings** → **Connected Accounts**
2. Click **Unlink** next to the provider you want to remove
3. Confirm the action

**Note:** You cannot unlink your last OAuth provider unless you have a password set. This prevents you from being locked out of your account.

### For Administrators

#### Enabling/disabling providers

Use the admin settings panel to control which OAuth providers are available to users. Disabling a provider:
- Hides the login/register buttons from users
- Prevents new OAuth authorizations
- Does NOT unlink existing accounts (users keep their linked providers)

#### User approval workflow

If you have **Require admin approval for new accounts** enabled, new OAuth users will:
1. Authenticate with their OAuth provider
2. Choose a username
3. Have their account created in **pending** status
4. Need admin approval before they can post or interact

The approval workflow is identical to regular email/password registrations.

## Auto-linking Behavior

When a user signs in with an OAuth provider:

1. **If their email matches an existing Murmur account:**
   - The OAuth provider is automatically linked to that account
   - User is logged in immediately
   - No duplicate account is created

2. **If their email is new:**
   - User is prompted to choose a username
   - A new account is created
   - The OAuth provider is linked to the new account

3. **If the OAuth provider doesn't share an email:**
   - User is shown an error message
   - No account is created or linked
   - All three supported providers (Google, Facebook, Apple) share emails by default

## Security Considerations

### State Tokens

OAuth flows use state tokens stored in the session to prevent CSRF attacks. The state includes:
- Random token for verification
- Provider name
- Original request timestamp

State tokens expire after the OAuth callback is processed.

### Password Management

- **OAuth-only accounts** (no password set) can only sign in via OAuth
- Users can set a password later via **Settings** → **Change Password**
- Having a password provides account recovery if OAuth providers become unavailable
- Users with multiple OAuth providers can unlink any provider except the last one (if no password is set)

### Email Verification

OAuth providers verify email addresses, so OAuth users are automatically verified. No email verification step is required.

### Data Storage

- OAuth credentials (client IDs, secrets) are stored in `etc/config.ini` (filesystem)
- User OAuth connections are stored in the `user_oauth_providers` database table
- No access tokens or refresh tokens are persisted - they're used only during the authentication flow

## Troubleshooting

### "Provider is not configured" error

This means credentials are missing from `etc/config.ini`. Check:
1. The config file exists and is readable
2. All required fields are present for the provider
3. For Apple: the private key file path is correct and the file is readable

### "Provider is disabled" error

The administrator has disabled this OAuth provider in the admin settings. Contact your instance administrator.

### "No email provided by OAuth provider" error

The OAuth provider didn't share an email address. This can happen if:
- The user declined the email permission during authorization
- The provider account doesn't have a verified email
- There's a misconfiguration in the OAuth app settings

### "Cannot unlink last OAuth provider" error

You're trying to unlink your only authentication method. To unlink your last OAuth provider:
1. Go to **Settings** → **Change Password**
2. Set a password for your account
3. Return to **Connected Accounts** and unlink the provider

### Redirect URI mismatch

If you see an error from the OAuth provider about redirect URIs:
1. Check that the redirect URI in your provider's app settings matches exactly: `https://your-domain.com/oauth/{provider}/callback`
2. Ensure HTTPS is configured (required for production OAuth)
3. For subdirectory installations, include the subdirectory in the URL

## Technical Details

### OAuth Flow

1. **Authorization Request**
   - User clicks OAuth button
   - `OAuthController::authorize()` generates state token
   - User is redirected to provider's authorization page

2. **Callback Handling**
   - Provider redirects back with authorization code
   - `OAuthController::callback()` validates state token
   - Exchanges code for access token
   - Fetches user info from provider
   - Checks for existing account by email

3. **Account Linking/Creation**
   - If email matches existing user: link provider and log in
   - If new user: redirect to username selection form
   - Store OAuth connection in `user_oauth_providers` table

4. **Username Completion** (new users only)
   - User submits username form
   - `OAuthController::complete()` creates account
   - Links OAuth provider
   - Logs user in

### Database Schema

```sql
CREATE TABLE user_oauth_providers (
    oauth_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_provider (user_id, provider),
    UNIQUE KEY unique_provider_user (provider, provider_user_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
```

### Services

- **`OAuthConfigService`** - Reads and validates OAuth credentials from config.ini
- **`OAuthService`** - Business logic for OAuth flows, account linking, username generation
- **`UserOAuthProviderMapper`** - Data access for OAuth provider connections

### Routes

- `GET /oauth/{provider}` - Initiate OAuth flow
- `GET /oauth/{provider}/callback` - Handle OAuth callback
- `GET /oauth/complete` - Username selection form (new users)
- `POST /oauth/complete` - Process username submission
- `POST /oauth/{provider}/unlink` - Unlink OAuth provider
- `GET /settings/connected-accounts` - Manage linked accounts

## Related Documentation

- [Installation Guide](installation.md) - Complete setup instructions
- [Administration Guide](administration.md) - Admin panel features and user management
- [Configuration Guide](configuration.md) - Database and settings reference
