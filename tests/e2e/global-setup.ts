import { Pool } from 'pg';

async function globalSetup() {
    const pool = new Pool({
        host: process.env.DB_HOST || 'localhost',
        port: parseInt(process.env.DB_PORT || '5432'),
        database: process.env.DB_NAME || 'fa-dev-bper',
        user: process.env.DB_USER || 'postgres',
        password: process.env.DB_PASSWORD || 'local!Passw0rd',
    });

    // Insert test fixtures with TEST_E2E_ prefix
    // Policy auth code fixture for newRetrievalCode tests
    await pool.query(`
        INSERT INTO ntt_bper.t_ath_policy_auth_code (code, insert_date, bper_contract_number, operation_type_code)
        VALUES ('RT_TEST_E2E_001', NOW(), 'TEST_E2E_054', '_RISTO')
        ON CONFLICT DO NOTHING
    `);

    await pool.end();
}

export default globalSetup;
