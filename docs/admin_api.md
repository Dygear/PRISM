# Admin API

## Introduction
It is important to note the purpose of the Administration API: **It is a cache, not a permanent data store**. It is designed such that external data sources can load objects into the cache for optimizing in two different ways: Fast Lookup, and On-Demand Lookup
Fast Lookup is ideal for storage methods which do not have inherently fast lookup, but are small enough to be easily cached in memory. The flat file implementation admin format of SourceMod fits this scenario. The entire file is read and stored in the cache.
On-Demand Lookup is ideal for storage methods which work over a network, or contain a large number of entries unsuitable for full retrieval, but fast atomic lookup. This is what SQL is good at. The SQL implementation of the SourceMod admin reader only stores admins in the cache once they have connected.
Note the difference between the two: In the first example, the cache is being used as a lookup mechanism itself, as well as storing the permissions. In the second example, the cache is being used only to store permissions, since lookup was done by the SQL server.

## Permissions
### Admin Flags
Permissions are granted through the AdminFlag data type. Each flag specifies an action or permission type that requires administrative access. For each admin, the set of flags which are enabled is called the admin's effective permissions.
AdminFlags are stored in a variety of ways to account for various coding flexibilities. Functions to convert in between these storage methods are available in the administration APIs for both C++ extensions and Pawn scripts.

* **AdminFlag**: Represents a single admin permission as part of an enumeration.
* **AdminFlag\[\]**: Represents an array of AdminFlags in no specific order, each member being a different flag enabled.
* **bool[]**: Represents a bit array, where each index i of the array specifies whether AdminFlag i is enabled (true) or disabled (false).
* **FlagBits**: Represents an unsigned 32bit integer, where each bit n of the integer specifies whether AdminFlag n is enabled (bit 1) or disabled (bit 0). Convenience macros are available for dealing with bitwise numbers. They are found in IAdminSystem.h and each start with ADMFLAG instead of Admin.

### Overrides
Permissions can also be granted through overrides. Overrides are specific permissions that override the default effective permissions. They can act on both commands and command groups (a command group is a set of commands under a common name). There are two types of overrides:

* **Global Overrides**: These override the default admin flag(s) required for a given command or command group.
* **Group Overrides**: These override whether members of the group can or cannot access a given command.

### Immunity
Immunity in SourceMod is based on immunity levels, which are arbitrary numbers greater than or equal to zero. If an admin has an immunity level of 0, that admin has no immunity. An admin can only target other admins if their immunity level is greater than or equal to the target's immunity level. This functionality can be tweaked via sm_immunity_mode in cfg/sourcemod.cfg.
When checking for immunity, the following heuristics are performed in this exact order:

* If the targeting client is not an admin, targeting fails.
* If the targetted client is not an admin, targeting succeeds.
* If the targeting client has ADMIN_ROOT, targeting succeeds.
* If the targetted client has ADMIN_IMMUNITY AND the targeting client does not have ADMIN_UNIMUNIZE, targeting fails.
* If no conclusion is reached via the previous steps, targeting succeeds.

The primary function for computing immunity is Plugin::canUserTarget().

### Cache Types
There are three cache types in the Administration API. To invalidate a cache means to entirely delete it and rebuild it from scratch. Rebuilding a cache means requesting each IAdminListener to rebuild that portion of the cache. Aside from rebuilding a cache, there are generally three major operations to each cache: Reading, Appending (adding new settings), and Deleting (deleting individual records).

* **Global Override Cache**: Holds global overrides.
	* Invalidation: Atomic
	* Readable: Yes
	* Appendable: Yes
	* Deletable: Yes
* **User Cache**: Holds Admin User objects.
	* Invalidation: Atomic
	* Readable: Yes
	* Appendable: Yes
	* Deletable: Yes
* **Group Cache**: Holds Admin Group objects.
	* Invalidation: Invalidates self and Admin Cache.
	* Readable: Yes
	* Appendable: Yes
	* Deletable: No

### Global Override Cache
The global override cache stores information about global overrides. Every operation to this cache occurs both retroactively and for future command creations. An override need not be tied to a command; however, if a command has the same name, the command will inherit that override's permissions.
### Writing/Appending
New entries to the global override cache will affect both already existing commands and future commands registered during the cache's lifetime.
Each command can be assigned any or no access flags.
### Deleting/Invalidation
Deleting a part or the whole of the global override cache will cause affected commands to revert to their original access behavior.
### Group Cache
The group cache scores all information about current groups. It is designed to be quite static, and thus modifying groups in memory is not very flexible.
Groups have a few important properties:

* **Add Flags**: These are flags added to any user who inherits the group.
* **Immunity**: Specifies rules for which admins cannot target this group.
* **Immunity Level**: If a user inheriting the group has a lower immunity level than this number, they inherit the new higher value from the group.
* **Specific**: Immunity from a list of specific groups.

### Writing/Appending

**Add Flags**: These can be modified at any time. However, users which inherit the group will not have their member permissions updated for performance reasons.
**Immunity**: Default and global immunity can be changed at any time. Users inheriting the group will be affected by the changes. Specific immunity can have new groups added (and it will affect member admins).
**Overrides**: Overrides can be changed between allow or deny at any time.

### Deleting/Invalidation
Per-group immunities cannot be removed once added. Groups themselves cannot be removed either, as this would be a potentially expensive operation. Instead, the entire group cache must be rebuilt (and with it, the admin cache). The goal with this design decision is to make group invalidations rare. In order to change a group fully, the entire admin cache must be "refreshed".

### Admin/User Cache
The user cache stores all information about currently known admins. Users stored can either be live (as in, their permissions are currently in use by a player), or idle (cached for future lookup).
Users have the following properties:

* **Flags**: These are the permission flags the admin inherits by default.
* **Effective Flags**: These are the permission flags the admin has at runtime. They are a combination of the base flags and the flags inherited from groups.
* **Groups**: The groups the admin is a member of.
* **Password**: A generic password that an authentication method might require.
* **Identities**: One or more mappings between authentication methods and unique identification strings.

### Writing/Appending

* **Flags**: Flags can be changed at any time. Changing flags will affect effective flags, even if done after groups are inherited.
* **Groups**: Groups can be inherited at any time, although a group cannot be inherited twice. Permissions inherited from groups cannot be updated (yet).
* **Password**: Passwords can be changed at any time.
* **Identities**: Identities can be added, but not changed or removed.

### Deleting/Invalidation
Groups cannot be removed from an admin's inherited group list. However, admins can be invalidated. This lets an authentication system, such as SQL, remove individual admins to refresh their privileges. Resources used by an admin object are always reclaimed efficiently.