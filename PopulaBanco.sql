-- Popula Banco
INSERT INTO TIPO_USUARIO (Id_Tipo, Nome_Tipo_Usuario, Valor_Refeicao) VALUES
(1, 'Admin', 0.00),
(2, 'Aluno', 3.90),
(3, 'Servidor', 12.00),
(4, 'Nutricionista', 0.00);

INSERT INTO FORMA_PAGAMENTO (Id_Forma_Pagamento, Nome_Forma_Pagamento) VALUES
(1, 'PIX'),
(2, 'Cartão'),
(3, 'Boleto'),
(4, 'Dinheiro');

INSERT INTO USUARIO (Num_Carteirinha, Nome_Usuario, CPF, SALDO) VALUES
(1001, 'Admin/Servidor/Aluno', '11122233344', 0.00),
(1002, 'Aluno', '22233344455', 0.00),
(1003, 'Servidora', '33344455566', 0.00),
(1004, 'Nutri/Servidora', '44455566677', 0.00),
(1005, 'Aluno', '55566677788', 0.00);

INSERT INTO TIPIFICA_USUARIO (Num_Carteirinha, Id_Tipo) VALUES
(1001, 1), 
(1002, 2), 
(1003, 3), 
(1004, 4), 
(1005, 2);


-- Testando usuário com dois tipos
INSERT INTO TIPIFICA_USUARIO (Num_Carteirinha, Id_Tipo) VALUES
(1004, 3); 

-- Com três tipos
INSERT INTO TIPIFICA_USUARIO (Num_Carteirinha, Id_Tipo) VALUES
(1001, 3);
INSERT INTO TIPIFICA_USUARIO (Num_Carteirinha, Id_Tipo) VALUES
(1001, 2);


-- Testes que estava fazendo
SELECT num_carteirinha, nome_usuario, cpf, saldo 
FROM USUARIO;

SELECT Num_Carteirinha, Id_Tipo
FROM TIPIFICA_USUARIO;


INSERT INTO RECARGAS (Id_Tipo_Pagamento, Valor, Num_Carteirinha) 
VALUES (1, 50.00, 1002);

-- Verifica se o saldo subiu 
SELECT Nome_Usuario, SALDO FROM USUARIO WHERE Num_Carteirinha = 1002;

INSERT INTO ACESSO (Num_Carteirinha) VALUES (1002);
-- Verifica se o banco calculou os R$ 3,90 sozinho
SELECT * FROM ACESSO WHERE Num_Carteirinha = 1002;
SELECT Nome_Usuario, SALDO FROM USUARIO WHERE Num_Carteirinha = 1002;

-- transferência
INSERT INTO TRANSFERENCIAS (Num_Remetente, Num_Destinatario, Valor) 
VALUES (1002, 1005, 10.00);
SELECT Num_Carteirinha, Nome_Usuario, SALDO FROM USUARIO WHERE Num_Carteirinha IN (1002, 1005);

-- Tentar recarregar um valor negativo
INSERT INTO RECARGAS (Id_Tipo_Pagamento, Valor, Num_Carteirinha) VALUES (1, -20.00, 1002);
-- Tentar transferir para si mesmo 
INSERT INTO TRANSFERENCIAS (Num_Remetente, Num_Destinatario, Valor) VALUES (1002, 1002, 5.00);
