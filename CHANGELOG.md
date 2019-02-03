# Change Log

## [v1.0.0-beta-10](https://github.com/prooph/event-store-client/tree/v1.0.0-beta-10)

[Full Changelog](https://github.com/prooph/event-store-client/compare/v1.0.0-beta-9...v1.0.0-beta-10)

**Implemented enhancements:**

- Event store persistent subscription [\#84](https://github.com/prooph/event-store-client/pull/84) ([prolic](https://github.com/prolic))

**Closed issues:**

- v1.0.0-beta-9 requires prooph/event-store dev-master instead of v8.0.0-beta-1 [\#82](https://github.com/prooph/event-store-client/issues/82)

**Merged pull requests:**

- Fix required version [\#83](https://github.com/prooph/event-store-client/pull/83) ([enumag](https://github.com/enumag))

## [v1.0.0-beta-9](https://github.com/prooph/event-store-client/tree/v1.0.0-beta-9) (2019-01-26)
[Full Changelog](https://github.com/prooph/event-store-client/compare/v1.0.0-beta-8...v1.0.0-beta-9)

**Implemented enhancements:**

- HTTP API UserCredentials [\#55](https://github.com/prooph/event-store-client/issues/55)
- refactor to use new async interfaces [\#81](https://github.com/prooph/event-store-client/pull/81) ([prolic](https://github.com/prolic))
- rename almost all exceptions [\#80](https://github.com/prooph/event-store-client/pull/80) ([prolic](https://github.com/prolic))
- update to v8 interfaces [\#79](https://github.com/prooph/event-store-client/pull/79) ([prolic](https://github.com/prolic))
- update projections managers [\#78](https://github.com/prooph/event-store-client/pull/78) ([prolic](https://github.com/prolic))
- use prooph/event-store v8 as core [\#74](https://github.com/prooph/event-store-client/pull/74) ([prolic](https://github.com/prolic))
- cleanup deps [\#73](https://github.com/prooph/event-store-client/pull/73) ([prolic](https://github.com/prolic))
- remove unused dependencies from composer.json [\#71](https://github.com/prooph/event-store-client/pull/71) ([SunMar](https://github.com/SunMar))
- Test php 7.3 on travis [\#66](https://github.com/prooph/event-store-client/pull/66) ([prolic](https://github.com/prolic))

**Fixed bugs:**

- Unit test problem [\#67](https://github.com/prooph/event-store-client/issues/67)
- fixed unit test problem [\#77](https://github.com/prooph/event-store-client/pull/77) ([prolic](https://github.com/prolic))

**Closed issues:**

- Optional logging dependencies [\#72](https://github.com/prooph/event-store-client/issues/72)
- EventAppearedOnCatchupSubscription uses internal class and non-interface [\#69](https://github.com/prooph/event-store-client/issues/69)
- php-fpm "oops, unknown child exited with 1" [\#68](https://github.com/prooph/event-store-client/issues/68)

**Merged pull requests:**

- move @internal to prevent code checker warnings [\#70](https://github.com/prooph/event-store-client/pull/70) ([SunMar](https://github.com/SunMar))

## [v1.0.0-beta-8](https://github.com/prooph/event-store-client/tree/v1.0.0-beta-8) (2018-12-08)
[Full Changelog](https://github.com/prooph/event-store-client/compare/v1.0.0-beta-7...v1.0.0-beta-8)

**Implemented enhancements:**

- refactor event appeared callbacks [\#65](https://github.com/prooph/event-store-client/pull/65) ([prolic](https://github.com/prolic))
- remove sync implementations [\#64](https://github.com/prooph/event-store-client/pull/64) ([prolic](https://github.com/prolic))

**Fixed bugs:**

- fix return type of readAllEventsForward in sync impl [\#63](https://github.com/prooph/event-store-client/pull/63) ([prolic](https://github.com/prolic))
- Add missing methods to EventStoreSyncConnection interface [\#61](https://github.com/prooph/event-store-client/pull/61) ([enumag](https://github.com/enumag))
- Remove type=JS from ProjectionsClient::updateQuery\(\) [\#59](https://github.com/prooph/event-store-client/pull/59) ([enumag](https://github.com/enumag))
- Fix SyncProjectionsManager [\#54](https://github.com/prooph/event-store-client/pull/54) ([enumag](https://github.com/enumag))

**Closed issues:**

- Projection reset issues [\#58](https://github.com/prooph/event-store-client/issues/58)
- Imposibility to edit native projections [\#56](https://github.com/prooph/event-store-client/issues/56)

**Merged pull requests:**

- Revert "Revert "Remove type=JS from ProjectionsClient::updateQuery\(\)"" [\#62](https://github.com/prooph/event-store-client/pull/62) ([prolic](https://github.com/prolic))
- Revert "Remove type=JS from ProjectionsClient::updateQuery\(\)" [\#60](https://github.com/prooph/event-store-client/pull/60) ([prolic](https://github.com/prolic))

## [v1.0.0-beta-7](https://github.com/prooph/event-store-client/tree/v1.0.0-beta-7) (2018-12-02)
[Full Changelog](https://github.com/prooph/event-store-client/compare/v1.0.0-beta-6...v1.0.0-beta-7)

**Implemented enhancements:**

- Rename Method StreamMetadata::build =\> StreamMetadata::create [\#47](https://github.com/prooph/event-store-client/issues/47)
- Remove type=JS for projections [\#46](https://github.com/prooph/event-store-client/issues/46)
- Add Sync Projections- / Query- / User-Managers [\#45](https://github.com/prooph/event-store-client/issues/45)
- Add ProjectionsManager::reset method [\#41](https://github.com/prooph/event-store-client/issues/41)
- implement sync projections- and query manager [\#53](https://github.com/prooph/event-store-client/pull/53) ([prolic](https://github.com/prolic))
- Implement PersistentSubscriptionsManager [\#51](https://github.com/prooph/event-store-client/pull/51) ([prolic](https://github.com/prolic))
- Rename Method StreamMetadata::build =\> StreamMetadata::create [\#49](https://github.com/prooph/event-store-client/pull/49) ([prolic](https://github.com/prolic))
- Remove type=JS for projections [\#48](https://github.com/prooph/event-store-client/pull/48) ([prolic](https://github.com/prolic))
- Add ProjectionsManager::reset method [\#42](https://github.com/prooph/event-store-client/pull/42) ([prolic](https://github.com/prolic))
- add all checkpoint const [\#40](https://github.com/prooph/event-store-client/pull/40) ([prolic](https://github.com/prolic))
- Renamed Uuid to Guid [\#39](https://github.com/prooph/event-store-client/pull/39) ([pkruithof](https://github.com/pkruithof))

**Fixed bugs:**

- fix EventStoreConnectionLogicHandler [\#52](https://github.com/prooph/event-store-client/pull/52) ([prolic](https://github.com/prolic))

**Closed issues:**

- Reconnecting for subscriptions in a cluster fails [\#37](https://github.com/prooph/event-store-client/issues/37)
- Output after the loop is closed [\#32](https://github.com/prooph/event-store-client/issues/32)

## [v1.0.0-beta-6](https://github.com/prooph/event-store-client/tree/v1.0.0-beta-6) (2018-11-19)
[Full Changelog](https://github.com/prooph/event-store-client/compare/v1.0.0-beta-5...v1.0.0-beta-6)

**Implemented enhancements:**

- Allow connection settings in connection string [\#27](https://github.com/prooph/event-store-client/issues/27)
- Use objects for json encoding [\#30](https://github.com/prooph/event-store-client/pull/30) ([prolic](https://github.com/prolic))
- change factory methods [\#28](https://github.com/prooph/event-store-client/pull/28) ([prolic](https://github.com/prolic))

**Fixed bugs:**

- Connecting to cluster does not work [\#34](https://github.com/prooph/event-store-client/issues/34)
- use Guid Codec for UUIDs [\#38](https://github.com/prooph/event-store-client/pull/38) ([prolic](https://github.com/prolic))
- Fix Operations Manager + Connection Factory [\#36](https://github.com/prooph/event-store-client/pull/36) ([prolic](https://github.com/prolic))
- Connection factory fixes [\#35](https://github.com/prooph/event-store-client/pull/35) ([pkruithof](https://github.com/pkruithof))
- Fix connection string usage [\#33](https://github.com/prooph/event-store-client/pull/33) ([prolic](https://github.com/prolic))

**Merged pull requests:**

- Small typo fix [\#31](https://github.com/prooph/event-store-client/pull/31) ([pkruithof](https://github.com/pkruithof))
- Fix readme [\#29](https://github.com/prooph/event-store-client/pull/29) ([enumag](https://github.com/enumag))

## [v1.0.0-beta-5](https://github.com/prooph/event-store-client/tree/v1.0.0-beta-5) (2018-11-09)
[Full Changelog](https://github.com/prooph/event-store-client/compare/v1.0.0-beta-4...v1.0.0-beta-5)

## [v1.0.0-beta-4](https://github.com/prooph/event-store-client/tree/v1.0.0-beta-4) (2018-11-03)
[Full Changelog](https://github.com/prooph/event-store-client/compare/v1.0.0-beta-3...v1.0.0-beta-4)

**Implemented enhancements:**

- Rename builders to factories [\#25](https://github.com/prooph/event-store-client/pull/25) ([enumag](https://github.com/enumag))
- use json flags [\#24](https://github.com/prooph/event-store-client/pull/24) ([prolic](https://github.com/prolic))

**Merged pull requests:**

- Update demo-sync.php [\#23](https://github.com/prooph/event-store-client/pull/23) ([enumag](https://github.com/enumag))

## [v1.0.0-beta-3](https://github.com/prooph/event-store-client/tree/v1.0.0-beta-3) (2018-10-28)
[Full Changelog](https://github.com/prooph/event-store-client/compare/v1.0.0-beta-2...v1.0.0-beta-3)

**Implemented enhancements:**

-  update EventStoreAsyncConnection interface  [\#21](https://github.com/prooph/event-store-client/pull/21) ([prolic](https://github.com/prolic))

**Fixed bugs:**

- bugfixes and tests [\#22](https://github.com/prooph/event-store-client/pull/22) ([prolic](https://github.com/prolic))
-  update EventStoreAsyncConnection interface  [\#21](https://github.com/prooph/event-store-client/pull/21) ([prolic](https://github.com/prolic))

## [v1.0.0-beta-2](https://github.com/prooph/event-store-client/tree/v1.0.0-beta-2) (2018-10-21)
[Full Changelog](https://github.com/prooph/event-store-client/compare/v1.0.0-beta-1...v1.0.0-beta-2)

**Fixed bugs:**

- Random test failure \#2 [\#18](https://github.com/prooph/event-store-client/issues/18)
- Random test failure [\#17](https://github.com/prooph/event-store-client/issues/17)
- bugfixes [\#20](https://github.com/prooph/event-store-client/pull/20) ([prolic](https://github.com/prolic))
- resolve random test failures [\#19](https://github.com/prooph/event-store-client/pull/19) ([prolic](https://github.com/prolic))

## [v1.0.0-beta-1](https://github.com/prooph/event-store-client/tree/v1.0.0-beta-1) (2018-10-17)
**Implemented enhancements:**

- add PersistentSubscriptionSettings Builder [\#11](https://github.com/prooph/event-store-client/issues/11)
- \[Feature request\] Throw error when callback doesn't return a promise [\#3](https://github.com/prooph/event-store-client/issues/3)
- Transaction handling [\#13](https://github.com/prooph/event-store-client/pull/13) ([prolic](https://github.com/prolic))
- remove callback hell [\#9](https://github.com/prooph/event-store-client/pull/9) ([prolic](https://github.com/prolic))
- Users management [\#8](https://github.com/prooph/event-store-client/pull/8) ([prolic](https://github.com/prolic))
- check event appeared callback returns a promise [\#4](https://github.com/prooph/event-store-client/pull/4) ([prolic](https://github.com/prolic))

**Fixed bugs:**

- use Promise\rethrow where applicable [\#10](https://github.com/prooph/event-store-client/issues/10)
- fix amphp problems [\#16](https://github.com/prooph/event-store-client/pull/16) ([prolic](https://github.com/prolic))
- use Promise\rethrow where applicable [\#14](https://github.com/prooph/event-store-client/pull/14) ([prolic](https://github.com/prolic))
- Travis + dependency hell [\#1](https://github.com/prooph/event-store-client/pull/1) ([prolic](https://github.com/prolic))

**Closed issues:**

- Persistent subscription doesn't handle events fast enough [\#5](https://github.com/prooph/event-store-client/issues/5)

**Merged pull requests:**

- Add docker-compose config for unit testing [\#15](https://github.com/prooph/event-store-client/pull/15) ([codeliner](https://github.com/codeliner))
- I DO NOT WANT TO DO THIS, UPPERCASE IS UTTERLY STUPID [\#12](https://github.com/prooph/event-store-client/pull/12) ([prolic](https://github.com/prolic))
- downgrade to protobuf2 [\#7](https://github.com/prooph/event-store-client/pull/7) ([prolic](https://github.com/prolic))



\* *This Change Log was automatically generated by [github_changelog_generator](https://github.com/skywinder/Github-Changelog-Generator)*
