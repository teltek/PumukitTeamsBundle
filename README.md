# TeamsBundle

Bundle for the [PuMuKIT Video Platform 6.0](https://github.com/pumukit/PuMuKIT/tree/6.0.x) or higher. It receives the
Microsoft Teams recordings that the users import from the PuMuKIT Teams app, and adds them to their personal series.

## Installation

```bash
composer require teltek/pumukit-teams-bundle
```

If Symfony Flex does not do it, add the bundle to `config/bundles.php`:

```php
Pumukit\TeamsBundle\PumukitTeamsBundle::class => ['all' => true],
```

Import its routes, for example in `config/routes/pumukit_teams.yaml`:

```yaml
pumukit_teams:
    resource: "@PumukitTeamsBundle/Resources/config/routing.yml"
    prefix:   /
```

Configure the app registration of the Teams app and the tenant of the institution, for example in
`config/packages/pumukit_teams.yaml`:

```yaml
pumukit_teams:
    client_id: '11111111-2222-3333-4444-555555555555'
    tenants: ['00000000-0000-0000-0000-000000000000']
```

Give the **Access the Teams API** permission (`ROLE_ACCESS_TEAMS_API`) to the permission profiles of the users that
import recordings.

## API

| Route | Parameters | Response |
|-------|------------|----------|
| `POST /teams/check` | `teamsId` | `{"imported": true}` or `{"imported": false}` |
| `POST /teams/import` | `teamsId`, `file`, optional `title` | `{"imported": true}`, or `409` if the recording was already imported |

Both routes need the Teams SSO token of the user in `Authorization: Bearer`, the one that
`microsoftTeams.authentication.getAuthToken()` returns in the Teams app. PuMuKIT accepts it if:

- it is signed with one of the Microsoft keys of `jwks_uri`, which are cached for a day and fetched again, at most
  every 5 minutes, when a token has an unknown key;
- it has not expired, with 2 minutes of margin;
- its tenant (`tid`) is one of `tenants`, and its issuer is the one of that tenant, version 1 or 2;
- its audience is `client_id` or the Application ID URI `api://{domain}/{client_id}`, and it has the `access_as_user`
  scope;
- its `email`, `preferred_username` or `upn` claim is the email of an enabled PuMuKIT user, without case distinction,
  with the `ROLE_ACCESS_TEAMS_API` permission.

Otherwise the routes answer `401` with `WWW-Authenticate: Bearer` if the token is not valid, `403` if the user is not
allowed, `400` if `teamsId` or the file is missing or the file is not valid, and `503` if the Microsoft keys cannot be
fetched. The errors are JSON: `{"error": "User not allowed"}`.

The recording becomes a video of the personal series of the user, created if needed with the *Videos of* title and the
full name of the user in every locale, with the `title` in every locale and the `teamsId` property, and an encoder job with `profile`,
`priority` and `language`.

## Configuration

| Option | Default | Description |
|--------|---------|-------------|
| `enable` | `true` | If false, the API gives 404, `client_id` and `tenants` are not required and nothing else of the bundle is loaded: no services, permission or cache pool |
| `client_id` | required if enabled | Application (client) ID of the Azure AD app registration of the Teams app |
| `tenants` | required if enabled | Azure AD tenant IDs whose users can import recordings |
| `jwks_uri` | `https://login.microsoftonline.com/common/discovery/v2.0/keys` | URL of the public keys that sign the Teams tokens |
| `profile` | `master_copy` | Encoder profile of the imported recordings |
| `priority` | `2` | Priority of the encoder jobs |
| `language` | `kernel.default_locale` | Language of the imported tracks |

The tenant ID of an institution is in **Azure Portal → Microsoft Entra ID → Overview**.

## Upgrade from 1.x

- The bundle requires PHP 8.5 and PuMuKIT 6.0.
- **The API needs the Teams SSO token.** Before, it trusted the `email` field of the request, so anybody could import
  files into the personal series of any user, or find out whether an email had an account. The `email` and `name`
  fields are ignored now. The PuMuKIT Teams app sends the token; its previous versions, which do not, get `401`, so
  update the app before or together with PuMuKIT.
- `client_id` and `tenants` are required, unless the new `enable` option is `false`.
- **The users need `ROLE_ACCESS_TEAMS_API`.** Before, the permission existed but it was not checked.
- The title of the imported recordings is saved in every locale; before, it was saved as a nested array.
- An already imported recording answers `409` instead of creating a second video, and a request without `teamsId` or
  file answers `400` instead of `500`. A file that the encoder rejects does not leave an empty video behind.
- The track language is `language` instead of `en`, and the encoder profile and priority are configurable.
- The personal series takes its title from the PuMuKIT translations in the configured locales.
