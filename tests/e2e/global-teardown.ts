import { Pool } from 'pg';

async function globalTeardown() {
    const pool = new Pool({
        host: process.env.DB_HOST || 'localhost',
        port: parseInt(process.env.DB_PORT || '5432'),
        database: process.env.DB_NAME || 'fa-dev-bper',
        user: process.env.DB_USER || 'postgres',
        password: process.env.DB_PASSWORD || 'local!Passw0rd',
    });

    await pool.query("DELETE FROM ntt_bper.t_ath_policy_auth_code WHERE bper_contract_number LIKE 'TEST_E2E_%'");
    await pool.query("DELETE FROM public.operation_audit_log WHERE operation_name LIKE 'TEST_E2E_%'");

    await pool.end();
}

export default globalTeardown;
