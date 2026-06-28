-- ADMIN, ALUNO, NUTRICIONISTA, SERVIDOR
CREATE TABLE TIPO_USUARIO(
    Id_Tipo int PRIMARY KEY,
    Nome_Tipo_Usuario varchar(20) NOT NULL,
    Valor_Refeicao NUMERIC(6, 2) NOT NULL
);

CREATE TABLE USUARIO(
    Num_Carteirinha INT PRIMARY KEY,
    Nome_Usuario VARCHAR(50) NOT NULL,
    CPF VARCHAR(11) UNIQUE NOT NULL,
    SALDO NUMERIC(6, 2) NOT NULL
);

-- Relaciona o usuário com o tipo para permitir múltiplos perfis
CREATE TABLE TIPIFICA_USUARIO(
    Num_Carteirinha INT REFERENCES USUARIO(Num_Carteirinha),
    Id_Tipo INT REFERENCES TIPO_USUARIO(Id_Tipo),
    PRIMARY KEY (Num_Carteirinha, Id_Tipo)
);

-- PIX, CARTAO, BOLETO
CREATE TABLE FORMA_PAGAMENTO(
    Id_Forma_Pagamento int PRIMARY KEY,
    Nome_Forma_Pagamento VARCHAR(20) NOT NULL
);

-- Historico de Recargas
CREATE TABLE RECARGAS(
    Id_Recarga int GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    Id_Tipo_Pagamento int REFERENCES FORMA_PAGAMENTO(Id_Forma_Pagamento) NOT NULL,
    Valor NUMERIC(6, 2) NOT NULL,
    Num_Carteirinha int REFERENCES USUARIO(Num_Carteirinha) NOT NULL,
    Data DATE NOT NULL DEFAULT CURRENT_DATE,
    Hora TIME DEFAULT CURRENT_TIME
);

-- Histórico de Refeições
CREATE TABLE ACESSO(
    Id_Refeicao int GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    Num_Carteirinha int REFERENCES USUARIO(Num_Carteirinha) NOT NULL,
    Data DATE NOT NULL DEFAULT CURRENT_DATE,
    Valor NUMERIC(6, 2),
    Hora TIME DEFAULT CURRENT_TIME
);

-- Histórico de Transferências
CREATE TABLE TRANSFERENCIAS(
    Num_Remetente int REFERENCES USUARIO(Num_Carteirinha) NOT NULL,
    Num_Destinatario int REFERENCES USUARIO(Num_Carteirinha) NOT NULL,
    Valor NUMERIC(6,2) NOT NULL,
    Data DATE NOT NULL DEFAULT CURRENT_DATE,
    Hora TIME DEFAULT CURRENT_TIME,
    PRIMARY KEY (Num_Remetente, Num_Destinatario, Data)
);

-- TRIGGERS ------------------------------------------------------------------

-- Trigger de limite de 4 tipos por usuário
CREATE OR REPLACE FUNCTION check_limite_tipos()
RETURNS TRIGGER AS $$
DECLARE
    total_tipos INT;
BEGIN
    SELECT COUNT(*) INTO total_tipos 
    FROM TIPIFICA_USUARIO 
    WHERE Num_Carteirinha = NEW.Num_Carteirinha;

    IF total_tipos >= 4 THEN
        RAISE EXCEPTION 'Operação cancelada: O usuário de carteirinha % já atingiu o limite máximo de 4 tipos cadastrados.', NEW.Num_Carteirinha;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER tg_limite_tipos_usuario
BEFORE INSERT ON TIPIFICA_USUARIO
FOR EACH ROW EXECUTE FUNCTION check_limite_tipos();


-- Trigger que define o valor descontado da refeição como o menor das opções
CREATE OR REPLACE FUNCTION calcula_e_desconta_refeicao()
RETURNS TRIGGER AS $$
DECLARE
    menor_valor NUMERIC(6, 2);
BEGIN
    -- Filtra apenas os tipos de consumidores reais (aluno/servidor) na hora de buscar o menor preço
    SELECT MIN(t.Valor_Refeicao) INTO menor_valor
    FROM TIPIFICA_USUARIO tu
    JOIN TIPO_USUARIO t ON tu.Id_Tipo = t.Id_Tipo
    WHERE tu.Num_Carteirinha = NEW.Num_Carteirinha
      AND UPPER(t.Nome_Tipo_Usuario) IN ('ALUNO', 'SERVIDOR'); -- Ignora o 0,00 do Admin/Nutri

    IF menor_valor IS NULL THEN
        RAISE EXCEPTION 'Usuário de carteirinha % não possui perfil de consumidor ativo para calcular a tarifa.', NEW.Num_Carteirinha;
    END IF;

    NEW.Valor := menor_valor;

    UPDATE USUARIO 
    SET SALDO = SALDO - menor_valor 
    WHERE Num_Carteirinha = NEW.Num_Carteirinha;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER tg_calcula_e_desconta_refeicao
BEFORE INSERT ON ACESSO
FOR EACH ROW EXECUTE FUNCTION calcula_e_desconta_refeicao();


-- Trigger para impedir saldo abaixo do limite permitido (negativo de até 1 refeição)
CREATE OR REPLACE FUNCTION valida_limite_saldo()
RETURNS TRIGGER AS $$
DECLARE
    menor_valor NUMERIC(6, 2);
    limite_negativo NUMERIC(6, 2);
BEGIN
    SELECT MIN(t.Valor_Refeicao) INTO menor_valor
    FROM TIPIFICA_USUARIO tu
    JOIN TIPO_USUARIO t ON tu.Id_Tipo = t.Id_Tipo
    WHERE tu.Num_Carteirinha = NEW.Num_Carteirinha;

    IF menor_valor IS NULL THEN
        limite_negativo := 0.00;
    ELSE
        limite_negativo := -menor_valor;
    END IF;

    IF NEW.SALDO < limite_negativo THEN
        RAISE EXCEPTION 'Saldo insuficiente! Saldo atual: R$ %. Limite permitido: R$ %.', OLD.SALDO, limite_negativo;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER tg_valida_limite_saldo
BEFORE UPDATE ON USUARIO
FOR EACH ROW EXECUTE FUNCTION valida_limite_saldo();


-- Trigger para impedir recargas e transferências com valor <= 0
CREATE OR REPLACE FUNCTION check_valores_positivos()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.Valor <= 0 THEN
        RAISE EXCEPTION 'O valor da operação deve ser maior que zero.';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER tg_check_recarga_positiva
BEFORE INSERT ON RECARGAS
FOR EACH ROW EXECUTE FUNCTION check_valores_positivos();

CREATE TRIGGER tg_check_transferencia_positiva
BEFORE INSERT ON TRANSFERENCIAS
FOR EACH ROW EXECUTE FUNCTION check_valores_positivos();


-- Trigger que atualiza o saldo após recarga bem-sucedida
CREATE OR REPLACE FUNCTION atualiza_saldo_recarga()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE USUARIO 
    SET SALDO = SALDO + NEW.Valor 
    WHERE Num_Carteirinha = NEW.Num_Carteirinha;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER tg_atualiza_saldo_recarga
AFTER INSERT ON RECARGAS
FOR EACH ROW EXECUTE FUNCTION atualiza_saldo_recarga();


-- Trigger para impedir transferência para si mesmo
-- Trigger que realiza a transferência (o limite de 1 transferência por dia por par é garantido pela PRIMARY KEY)
CREATE OR REPLACE FUNCTION gerencia_transferencia()
RETURNS TRIGGER AS $$
DECLARE
    saldo_remetente NUMERIC(6, 2);
    ja_transferiu INT;
BEGIN
    SELECT SALDO INTO saldo_remetente FROM USUARIO WHERE Num_Carteirinha = NEW.Num_Remetente;
    -- Impede transferência se o valor for maior que o saldo real do usuário
    IF NEW.Valor > saldo_remetente THEN
        RAISE EXCEPTION 'Transferência negada: Seu saldo atual é de R$ %, insuficiente para transferir R$ %.', saldo_remetente, NEW.Valor;
    END IF;

    -- Impede auto-transferência
    IF NEW.Num_Remetente = NEW.Num_Destinatario THEN
        RAISE EXCEPTION 'Não é possível transferir valores para sua própria carteirinha.';
    END IF;

    -- Verifica limite de 1 por dia (PK já faz isso)
    SELECT COUNT(*) INTO ja_transferiu
    FROM TRANSFERENCIAS
    WHERE Num_Remetente = NEW.Num_Remetente AND Num_Destinatario = NEW.Num_Destinatario AND Data = NEW.Data;

    IF ja_transferiu > 0 THEN
        RAISE EXCEPTION 'Operação negada: Você já realizou uma transferência para este usuário hoje. Limite de 1 por dia.';
    END IF;

    -- executa a movimentação
    UPDATE USUARIO SET SALDO = SALDO - NEW.Valor WHERE Num_Carteirinha = NEW.Num_Remetente;
    UPDATE USUARIO SET SALDO = SALDO + NEW.Valor WHERE Num_Carteirinha = NEW.Num_Destinatario;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER tg_gerencia_transferencia
BEFORE INSERT ON TRANSFERENCIAS
FOR EACH ROW EXECUTE FUNCTION gerencia_transferencia();

-- Impedir Admin e Nutricionista de acessar a catraca (Busca por nome para não depender de IDs fixos)
-- Valida horário e quantidade de acesso por horário (janta/almoço)
CREATE OR REPLACE FUNCTION valida_tipo_acesso()
RETURNS TRIGGER AS $$
DECLARE
    possui_tipo_valido INT;
    turno_atual VARCHAR(10) := 'FORA';
    refeicoes_no_turno INT;
BEGIN
    -- Identifica o turno baseado na HORA do INSERT (se omitido, pega o CURRENT_TIME do sistema)
    IF NEW.Hora BETWEEN '10:00:00' AND '14:30:00' THEN
        turno_atual := 'ALMOCO';
    ELSIF NEW.Hora BETWEEN '17:00:00' AND '20:30:00' THEN
        turno_atual := 'JANTA';
    END IF;

    -- Se estiver fora das janelas de refeição, nem tenta liberar
    IF turno_atual = 'FORA' THEN
        RAISE EXCEPTION 'Acesso negado: O restaurante está fechado neste horário (%s).', TO_CHAR(NEW.Hora, 'HH24:MI:SS');
    END IF;

    -- Verifica se o usuário já comeu nesse turno hoje
    IF turno_atual = 'ALMOCO' THEN
        SELECT COUNT(*) INTO refeicoes_no_turno
        FROM ACESSO
        WHERE Num_Carteirinha = NEW.Num_Carteirinha
          AND Data = NEW.Data
          AND Hora BETWEEN '10:00:00' AND '14:30:00';
    ELSE -- janta
        SELECT COUNT(*) INTO refeicoes_no_turno
        FROM ACESSO
        WHERE Num_Carteirinha = NEW.Num_Carteirinha
          AND Data = NEW.Data
          AND Hora BETWEEN '17:00:00' AND '20:30:00';
    END IF;

    IF refeicoes_no_turno > 0 THEN
        RAISE EXCEPTION 'Acesso negado: Você já realizou uma refeição no turno do % hoje.', turno_atual;
    END IF;

    -- bloqueio para Admin/Nutri
    SELECT COUNT(*) INTO possui_tipo_valido
    FROM TIPIFICA_USUARIO tu
    JOIN TIPO_USUARIO t ON tu.Id_Tipo = t.Id_Tipo
    WHERE tu.Num_Carteirinha = NEW.Num_Carteirinha
      AND UPPER(t.Nome_Tipo_Usuario) IN ('ALUNO', 'SERVIDOR');

    IF possui_tipo_valido = 0 THEN
        RAISE EXCEPTION 'Acesso negado: Usuário não possui perfil de consumidor (Aluno/Servidor) ativo.';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER tg_valida_tipo_acesso
BEFORE INSERT ON ACESSO
FOR EACH ROW EXECUTE FUNCTION valida_tipo_acesso();


-- Impedir Admin e Nutricionista de realizar recargas
CREATE OR REPLACE FUNCTION valida_tipo_recarga()
RETURNS TRIGGER AS $$
DECLARE
    possui_tipo_financeiro INT;
BEGIN
    SELECT COUNT(*) INTO possui_tipo_financeiro
    FROM TIPIFICA_USUARIO tu
    JOIN TIPO_USUARIO t ON tu.Id_Tipo = t.Id_Tipo
    WHERE tu.Num_Carteirinha = NEW.Num_Carteirinha
      AND UPPER(t.Nome_Tipo_Usuario) IN ('ALUNO', 'SERVIDOR'); -- Blindado contra Case-Sensitive

    IF possui_tipo_financeiro = 0 THEN
        RAISE EXCEPTION 'Recarga negada: Este usuário não possui uma carteirinha financeira ativa.';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER tg_valida_tipo_recarga
BEFORE INSERT ON RECARGAS
FOR EACH ROW EXECUTE FUNCTION valida_tipo_recarga();