# Releasing MathPHP

This checklist is the source of truth for publishing a compatible MathPHP
ecosystem release. It is intentionally manual: release automation must not
grant repository access, issue package credentials, process payments, or change
the visibility of a private repository.

## Release order

Publish the packages in dependency order:

1. `mathphp/mathphp` (public core)
2. `mathphp/mathphp-units` (private, optional)
3. `mathphp/mathphp-visuals` (private, optional)
4. `mathphp/mathphp-explaining` (private, optional)
5. `mathphp/mathphp-docs` and `mathphp/mathphp-website`

The core tag is the compatibility anchor. Add-on lockfiles should be refreshed
to the intended core tag or commit before their tags are created. Explaining
must also be checked against the visuals and units revisions it supports.

## Checklist

For each package:

1. Update the changelog or release notes with user-visible changes and breaking
   changes.
2. Run the package's documented validation commands. Core requires
   `composer quality`; add-ons require `composer validate --strict --check-lock`,
   `composer test`, and `composer analyse` (or the equivalent CI job).
3. Confirm the PHP support range and Composer constraints match the code.
4. Create an annotated SemVer tag only after CI is green, then push the tag.
5. Confirm the GitHub tag/release points at the intended commit and that private
   repositories remain private.

Do not use a moving `dev-main` reference in a production release. Applications
should pin a tag or commit for every package they install.

## Website and deployment verification

After updating the website's reviewed core lockfile or optional package refs:

1. Push the website change and wait for its CI job, including the HTTP smoke
   suite.
2. Deploy through Nova.
3. Check `GET /?api=health`, `GET /?api=version`, and
   `GET /?api=capabilities`.
4. Confirm each reported package reference matches the intended tag/commit.
5. Run `tools/http-smoke.php` with `MATHPHP_SMOKE_REQUIRE_OPTIONAL=1` when the
   add-ons are installed.
6. Verify the Playground at desktop and mobile widths, including an unavailable
   response when an optional package is absent.

The `.mathphp-revision` marker in an optional package root is the runtime source
of truth when a deployment checks out a specific commit. A lockfile alone can
describe a dependency without proving which add-on source is executing.

## Access and licensing boundary

The private add-on license and repository permissions remain authoritative. The
website and release checklist do not implement checkout, sponsorship, user
provisioning, token issuance, access revocation, or update entitlement logic.
Choose and document that business model separately before adding any related
automation.
