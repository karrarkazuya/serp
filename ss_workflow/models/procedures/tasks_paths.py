# -*- coding: utf-8 -*-

from odoo import models, fields, api
from odoo.exceptions import ValidationError


class SswTasksPaths(models.Model):
    _name = 'ssw.proc.taskpaths'
    _description = 'Task paths'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name = fields.Char(string='Title')

    task_id = fields.Many2one(
        'ssw.proc.tasks',
        string='Parent task',
        required=True,
        tracking=True,
    )
    target_task_id = fields.Many2one(
        'ssw.proc.tasks',
        string='Target task',
        required=True,
        tracking=True,
    )