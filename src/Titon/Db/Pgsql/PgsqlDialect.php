<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Pgsql;

use Titon\Db\Driver\Dialect\AbstractPdoDialect;
use Titon\Db\Driver\Dialect\Statement;
use Titon\Db\Driver\Schema;
use Titon\Db\Query;

/**
 * Inherit the default dialect rules and override for PostgreSQL specific syntax.
 *
 * @package Titon\Db\Pgsql
 */
class PgsqlDialect extends AbstractPdoDialect {

    const CONCURRENTLY = 'concurrently';
    const CONTINUE_IDENTITY = 'continueIdentity';
    const DELETE_ROWS = 'deleteRows';
    const DISTINCT_ON = 'distinctOn';
    const DROP = 'drop';
    const FOR_UPDATE_LOCK = 'forUpdateLock';
    const FOR_SHARE_LOCK = 'forShareLock';
    const INHERITS = 'inherits';
    const IS_GLOBAL = 'global';
    const LOCAL = 'local';
    const MATCH = 'match';
    const MATCH_FULL = 'matchFull';
    const MATCH_PARTIAL = 'matchPartial';
    const MATCH_SIMPLE = 'matchSimple';
    const ON_COMMIT = 'onCommit';
    const ONLY = 'only';
    const PRESERVE_ROWS = 'preserveRows';
    const RESTART_IDENTITY = 'restartIdentity';
    const RETURNING = 'returning';
    const SET_DEFAULT = 'setDefault';
    const TABLESPACE = 'tablespace';
    const UNIQUE = 'unique';
    const UNLOGGED = 'unlogged';
    const WITH = 'with';
    const WITH_OIDS = 'withOids';
    const WITHOUT_OIDS = 'withoutOids';

    /**
     * Configuration.
     *
     * @type array
     */
    protected $_config = [
        'quoteCharacter' => '"',
        'virtualJoins' => true
    ];

    /**
     * Modify clauses and keywords.
     */
    public function initialize() {
        parent::initialize();

        $this->addClauses([
            self::DISTINCT_ON   => 'DISTINCT ON (%s)',
            self::JOIN_STRAIGHT => 'INNER JOIN %s ON %s',
            self::MATCH         => '%s',
            self::NOT_REGEXP    => '%s !~* ?',
            self::RETURNING     => 'RETURNING %s',
            self::REGEXP        => '%s ~* ?',
            self::RLIKE         => '%s ~* ?',
            self::UNIQUE_KEY    => 'UNIQUE (%2$s)',
            self::WITH          => '%s'
        ]);

        $this->addKeywords([
            self::CONCURRENTLY      => 'CONCURRENTLY',
            self::CONTINUE_IDENTITY => 'CONTINUE IDENTITY',
            self::DELETE_ROWS       => 'DELETE ROWS',
            self::DROP              => 'DROP',
            self::FOR_SHARE_LOCK    => 'FOR SHARE',
            self::FOR_UPDATE_LOCK   => 'FOR UPDATE',
            self::INHERITS          => 'INHERITS',
            self::IS_GLOBAL         => 'GLOBAL',
            self::LOCAL             => 'LOCAL',
            self::MATCH_FULL        => 'MATCH FULL',
            self::MATCH_PARTIAL     => 'MATCH PARTIAL',
            self::MATCH_SIMPLE      => 'MATCH SIMPLE',
            self::ON_COMMIT         => 'ON COMMIT',
            self::ONLY              => 'ONLY',
            self::PRESERVE_ROWS     => 'PRESERVE ROWS',
            self::RESTART_IDENTITY  => 'RESTART IDENTITY',
            self::SET_DEFAULT       => 'SET DEFAULT',
            self::TABLESPACE        => 'TABLESPACE',
            self::UNIQUE            => 'UNIQUE',
            self::UNLOGGED          => 'UNLOGGED',
            self::WITH_OIDS         => 'WITH OIDS',
            self::WITHOUT_OIDS      => 'WITHOUT OIDS'
        ]);

        $this->addStatements([
            Query::INSERT        => new Statement('INSERT INTO {table} {fields} VALUES {values}'),
            Query::SELECT        => new Statement('SELECT {distinct} {fields} FROM {table} {joins} {where} {groupBy} {having} {compounds} {orderBy} {limit} {lock}'),
            Query::UPDATE        => new Statement('UPDATE {only} {table} SET {fields} {where}'),
            Query::DELETE        => new Statement('DELETE FROM {only} {table} {joins} {where}'),
            Query::TRUNCATE      => new Statement('TRUNCATE {only} {table} {identity} {action}'),
            Query::CREATE_TABLE  => new Statement("CREATE {type} {temporary} {unlogged} TABLE IF NOT EXISTS {table} (\n{columns}{keys}\n) {options}"),
            Query::CREATE_INDEX  => new Statement('CREATE {type} INDEX {concurrently} {index} ON {table} ({fields})'),
            Query::DROP_TABLE    => new Statement('DROP TABLE IF EXISTS {table} {action}'),
            Query::DROP_INDEX    => new Statement('DROP INDEX {concurrently} IF EXISTS {index} {action}')
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function formatColumns(Schema $schema) {
        $columns = [];

        foreach ($schema->getColumns() as $column => $options) {
            $type = $options['type'];
            $dataType = $this->getDriver()->getType($type);
            $options = $options + $dataType->getDefaultOptions();

            if ($type === 'int') {
                $type = 'integer';
            }

            if (!empty($options['length'])) {
                $type .= '(' . $options['length'] . ')';
            }

            $output = [$this->quote($column), $type];

            if (!empty($options['collate'])) {
                $output[] = sprintf($this->getClause(self::COLLATE), $options['collate']);
            }

            if (!empty($options['constraint'])) {
                $output[] = sprintf($this->getClause(self::CONSTRAINT), $this->quote($options['constraint']));
            }

            // Primary and uniques can't be null
            if (!empty($options['primary']) || !empty($options['unique'])) {
                $output[] = $this->getKeyword(self::NOT_NULL);
            } else {
                $output[] = $this->getKeyword(empty($options['null']) ? self::NOT_NULL : self::NULL);
            }

            if (array_key_exists('default', $options)) {
                $output[] = $this->formatDefault($options['default']);
            }

            $columns[] = trim(implode(' ', $output));
        }

        return implode(",\n", $columns);
    }

}