from odoo import models, fields, exceptions, api
from datetime import datetime, timedelta
from pytz import timezone


class mail_message(models.Model):
    _inherit = 'mail.message'
    