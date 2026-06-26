-- ADMIN, ALUNO, NUTRICIONISTA, SERVIDOR
CREATE TABLE TIPO_USUARIO(
    Id_Tipo int PRIMARY KEY,
    Nome_Tipo_Usuario varchar(20) NOT NULL,
    Valor_Refeicao NUMERIC(6, 2) NOT NULL
);

-- 
CREATE TABLE USUARIO(
    Num_Carteirinha INT PRIMARY KEY,
    Nome_Usuario VARCHAR(50) NOT NULL,
    CPF VARCHAR(11) UNIQUE NOT NULL,
    SALDO NUMERIC(6, 2) NOT NULL
);

-- Relaciona o usuário com o tipo para permitir 
CREATE TABLE TIPIFICA_USUARIO(
    Num_Carteirinha REFERENCES USUARIO(Num_Carteirinha),
    Id_Tipo REFERENCES TIPO_USUARIO(Id_Tipo),
    PRIMARY KEY (Num_Carteirinha, Id_Tipo)
);

-- PIX, CARTAO, BOLETO
CREATE TABLE FORMA_PAGAMENTO(
    Id_Forma_Pagamento INT PRIMARY KEY,
    Nome_Forma_Pagamento VARCHAR(20) NOT NULL
);

-- Historico de Recargas
CREATE TABLE RECARGAS(
    Id_Recarga int PRIMARY KEY,
    Id_Tipo_Pagamento int REFERENCES FORMA_PAGAMENTO(Id_Forma_Pagamento)NOT NULL,
    Valor NUMERIC(6, 2) NOT NULL,
    Num_Carteirinha int REFERENCES USUARIO(Num_Carteirinha) NOT NULL,
    Data DATE NOT NULL,
    Hora TIME DEFAULT CURRENT_TIME
);

-- Histórico de Refeições
CREATE TABLE ACESSO(
    Id_Refeicao int PRIMARY KEY,
    Num_Carteirinha int REFERENCES USUARIO(Num_Carteirinha) NOT NULL,
    Data DATE NOT NULL,
    Valor NUMERIC(6, 2), -- Vai ter que fazer um trigger ON INSERT pra calcular o valor de cada refeição
    Hora TIME DEFAULT CURRENT_TIME
);

-- Histórico de Transferências
CREATE TABLE TRANSFERENCIAS(
    Num_Remetente int REFERENCES USUARIO(Num_Carteirinha) NOT NULL,
    Num_Destinatario int REFERENCES USUARIO(Num_Carteirinha) NOT NULL,
    Valor NUMERIC(6,2) NOT NULL,
    Data DATE NOT NULL,
    Hora TIME DEFAULT CURRENT_TIME,
    PRIMARY KEY (Num_Remetente, Num_Destinatario, Data)
);

-- Trigger de limite de 4 usuários
-- Trigger que define o valor descontado da refeição como o menor das opções
-- Trigger para impedir saldo negativo inferior ao valor de uma refeição (-menor valor do tipo usuário daquele user)
-- Trigger para impedir recargas, transferências e acessos com valor negativo
-- Trigger para impedir transferência para si mesmo
