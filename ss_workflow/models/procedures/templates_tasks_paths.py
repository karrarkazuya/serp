# -*- coding: utf-8 -*-

from odoo import models, fields, api
from odoo.exceptions import ValidationError


class SswTemplatesTasksPaths(models.Model):
    _name = 'ssw.proc.templates.taskpaths'
    _description = 'Task path'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name = fields.Char(string='Title')

    task_id = fields.Many2one(
        'ssw.proc.templates.tasks',
        string='Parent task',
        required=True,
        ondelete='cascade',
        tracking=True,
    )
    template_id = fields.Many2one(
        'ssw.proc.templates',  # adjust to your real model name
        related='task_id.template_id',
        store=True,
        readonly=True,
    )
    parent_task_sequance = fields.Integer(
        related='task_id.task_sequance',
        store=True,
        readonly=True,
    )
    target_task_id = fields.Many2one(
        'ssw.proc.templates.tasks',
        string='Target task',
        domain="[('template_id', '=', template_id), ('task_sequance', '>', parent_task_sequance)]",
        required=True,
        tracking=True,
    )

    @api.constrains('task_id', 'target_task_id')
    def _check_target_task(self):
        for rec in self:
            if not rec.target_task_id:
                continue
            if rec.target_task_id.template_id != rec.task_id.template_id:
                raise ValidationError(
                    "Target task must belong to the same template as the parent task."
                )
            if rec.target_task_id.task_sequance <= rec.task_id.task_sequance:
                raise ValidationError(
                    "Target task must come after the parent task in sequence."
                )
            if rec.target_task_id == rec.task_id:
                raise ValidationError("A task cannot point to itself.")