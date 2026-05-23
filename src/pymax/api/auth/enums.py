from enum import Enum


class AuthType(str, Enum):
    START_AUTH = "START_AUTH"
    CHECK_CODE = "CHECK_CODE"
    REGISTER = "REGISTER"
    RESEND = "RESEND"


class Capability(int, Enum):
    DEFAULT = 0  # В душе не чаю что это такое но при первой установке 2фа там 0 3 4 так что пусть будет дефолт
    ESIA_VERIFIED_FLAG = 1
    SECOND_FACTOR_PASSWORD_ENABLED = 2
    SECOND_FACTOR_HAS_EMAIL = 3
    SECOND_FACTOR_HAS_HINT = 4
    REMOVE_2FA = 5
