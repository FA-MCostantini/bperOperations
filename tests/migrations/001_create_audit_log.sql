CREATE TABLE IF NOT EXISTS public.operation_audit_log (
    id             SERIAL PRIMARY KEY,
    operation_name TEXT NOT NULL,
    payload        JSONB NOT NULL,
    user_id        INT NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_log_operation
    ON public.operation_audit_log (operation_name);

CREATE INDEX IF NOT EXISTS idx_audit_log_created_at
    ON public.operation_audit_log (created_at DESC);
